<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

use Ivory\Utils\StringUtils;

/**
 * Parser of SQL pattern strings into {@link SqlPattern} objects.
 */
class SqlPatternParser implements ISqlPatternParser
{
    public function parse(string $sqlPatternString): SqlPattern
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
	    	            (                                   #       (1)
	    	              [[:alpha:]_] [[:alnum:]_]*        #       either a token
	    	              |                                 #       or
	    	              " (?: [^"]+ | "" )* "             #       a quoted string
	    	            )                                   #
	    	            \.                                  #     separated from type name with a dot
	    	          )?                                    #
	    	          (                                     #     (2) type name
	    	            [[:alpha:]_] [[:alnum:]_]*          #       either a token
	    	            |                                   #       or
	    	            " (?: [^"]+ | "" )* "               #       a quoted string
	    	          )                                     #
	    	          |                                     #
	    	          \{ ([^}]+) \}                         #     (3) or just anything enclosed in curly braces, taken as is
	    	        )                                       #
	    	        ( \[\] )*                               #     (4) optionally ended with pairs of brackets
	    	      )?                                        #
	    	      ( \? )?                                   #   (5) optional question mark for loose type mode
	    	      (?: : ( [[:alpha:]_] [[:alnum:]_]* ) )?   #   (6) optional parameter name, starting with a letter or underscore
	    	    |                                           # or
	    	    ( % )                                       # (7) another percent sign -> literal %
	    	  )
	    	 ~xu',
            function ($matchWithOffsets) use (&$positionalPlaceholders, &$namedPlaceholderMap, &$rawOffsetDelta) {
                if (isset($matchWithOffsets[7])) {
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
                $looseTypeMode = !empty($matchWithOffsets[5][0]);
                if (isset($matchWithOffsets[6])) {
                    $name = $matchWithOffsets[6][0];
                    $plcHld = new SqlPatternPlaceholder(
                        $offset, $name, $typeName, $typeNameQuoted, $schemaName, $schemaNameQuoted, $looseTypeMode
                    );
                    if (!isset($namedPlaceholderMap[$name])) {
                        $namedPlaceholderMap[$name] = [];
                    }
                    $namedPlaceholderMap[$name][] = $plcHld;
                } else {
                    $pos = count($positionalPlaceholders);
                    $plcHld = new SqlPatternPlaceholder(
                        $offset, $pos, $typeName, $typeNameQuoted, $schemaName, $schemaNameQuoted, $looseTypeMode
                    );
                    $positionalPlaceholders[] = $plcHld;
                }
                $rawOffsetDelta -= strlen($matchWithOffsets[0][0]); // put no characters instead of the whole match
                return '';
            },
            $sqlPatternString
        );

        return new SqlPattern($sqlTorso, $positionalPlaceholders, $namedPlaceholderMap);
    }

    /**
     * @param string|null $str
     * @param bool|null $quoted
     * @return string|null <tt>null</tt> iff <tt>$str</tt> is <tt>null</tt>
     */
    private function unquoteString(?string $str, ?bool &$quoted = null): ?string
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
