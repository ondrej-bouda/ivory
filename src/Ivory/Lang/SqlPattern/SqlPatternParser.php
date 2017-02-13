<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Utils\StringUtils;

class SqlPatternParser
{
    /**
     * Parses an SQL pattern string to an {@link SqlPattern} object.
     *
     * @param string $sqlPattern
     * @return SqlPattern
     */
    public function parse(string $sqlPattern): SqlPattern
    {
        $positionalPlaceholders = [];
        $namedPlaceholderMap = [];
        $rawOffsetDelta = 0;

        $sqlTorso = StringUtils::pregReplaceCallbackWithOffset(
            '~
	    	  %                                             # the percent sign introducing the sequence
	    	  (?: (?! % )                                   # anything but another percent sign -> placeholder
	    	      (?:                                       #   optional type specification
	    	        (?:                                     #
	    	          (?:                                   #     optional schema name
	    	            ( [[:alpha:]_] [[:alnum:]_]*        #       either a token
	    	              |                                 #       or
	    	              " (?: [^"]+ | "" )* "             #       a quoted string
	    	            )                                   #
	    	            \.                                  #     separated from type name with a dot
	    	          )?                                    #
	    	          (                                     #     type name
	    	            [[:alpha:]_] [[:alnum:]_]*          #       either a token
	    	            |                                   #       or
	    	            " (?: [^"]+ | "" )* "               #       a quoted string
	    	          )                                     #
	    	          |                                     #
	    	          \{ ([^}]+) \}                         #     or just anything enclosed in curly braces, taken as is
	    	        )                                       #
	    	        ( \[\] )*                               #     optionally ended with pairs of brackets
	    	      )?                                        #
	    	      (?: : ( [[:alpha:]_] [[:alnum:]_]* ) )?   #   optional parameter name, starting with a letter or underscore
	    	    |                                           # or
	    	    ( % )                                       # another percent sign -> literal %
	    	  )
	    	 ~xu',
            function ($matchWithOffsets) use (&$positionalPlaceholders, &$namedPlaceholderMap, &$rawOffsetDelta) {
                if (isset($matchWithOffsets[6])) {
                    $rawOffsetDelta--; // put one character instead of two
                    return '%';
                }

                $offset = $matchWithOffsets[0][1] + $rawOffsetDelta;

                if (strlen(($matchWithOffsets[3][0] ?? '')) > 0) {
                    $schemaName = null;
                    $schemaNameQuoted = false;
                    $typeName = $matchWithOffsets[3][0];
                    $typeNameQuoted = false;
                } else {
                    $schemaItem = (!empty($matchWithOffsets[1][0]) ? $matchWithOffsets[1][0] : null); // correctness: empty() is OK as the schema name may not be "0"
                    $schemaName = $this->unquoteString($schemaItem, $schemaNameQuoted);
                    $typeItem = (!empty($matchWithOffsets[2][0]) ? $matchWithOffsets[2][0] : null); // correctness: empty() is OK as the type name may not be "0"
                    $typeName = $this->unquoteString($typeItem, $typeNameQuoted);
                }

                if (!empty($matchWithOffsets[4][0])) {
                    $typeName .= '[]'; // regardless of the number of bracket pairs, just a single pair is taken
                }
                if (isset($matchWithOffsets[5])) {
                    $name = $matchWithOffsets[5][0];
                    $plcHld = new SqlPatternPlaceholder(
                        $offset, $name, $typeName, $typeNameQuoted, $schemaName, $schemaNameQuoted
                    );
                    if (!isset($namedPlaceholderMap[$name])) {
                        $namedPlaceholderMap[$name] = [];
                    }
                    $namedPlaceholderMap[$name][] = $plcHld;
                } else {
                    $pos = count($positionalPlaceholders);
                    $plcHld = new SqlPatternPlaceholder(
                        $offset, $pos, $typeName, $typeNameQuoted, $schemaName, $schemaNameQuoted
                    );
                    $positionalPlaceholders[] = $plcHld;
                }
                $rawOffsetDelta -= strlen($matchWithOffsets[0][0]); // put no characters instead of the whole match
                return '';
            },
            $sqlPattern
        );

        return new SqlPattern($sqlTorso, $positionalPlaceholders, $namedPlaceholderMap);
    }

    private function unquoteString($str, &$quoted = null)
    {
        if ($str && $str[0] == '"') {
            assert($str[strlen($str) - 1] == '"');
            $quoted = true;
            return str_replace('""', '"', substr($str, 1, -1));
        } else {
            $quoted = false;
            return $str;
        }
    }
}
