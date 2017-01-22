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
    public function parse(string $sqlPattern) : SqlPattern
    {
	    $positionalPlaceholders = [];
	    $namedPlaceholderMap = [];
	    $rawOffsetDelta = 0;

	    $rawSql = StringUtils::pregReplaceCallbackWithOffset(
	    	'~
	    	  %                                             # the percent sign introducing the sequence
	    	  (?: (?! % )                                   # anything but another percent sign -> placeholder
	    	      (?:                                       #   optional type name
	    	        ( [[:alpha:]_]                          #     starting with a letter or underscore
	    	          (?: [[:alnum:]_.]* [[:alnum:]_] )?    #     dots allowed inside the type name
	    	        )                                       #
	    	        ( \[\] )*                               #     optionally ended with pairs of brackets
	    	      )?                                        #
	    	      (?: : ( [[:alpha:]_] [[:alnum:]_]* ) )?   #   optional parameter name, starting with a letter or underscore
	    	    | ( % )                                     # or another percent sign -> literal %
	    	  )
	    	 ~x',
		    function ($matchWithOffsets) use (&$positionalPlaceholders, &$namedPlaceholderMap, &$rawOffsetDelta) {
			    if (isset($matchWithOffsets[4])) {
				    $rawOffsetDelta--; // put one character instead of two
				    return '%';
			    }
			    else {
			    	$offset = $matchWithOffsets[0][1] + $rawOffsetDelta;
				    $type = (!empty($matchWithOffsets[1][0]) ? $matchWithOffsets[1][0] : null); // correctness: empty() is OK as the type name may not be "0"
                    if (!empty($matchWithOffsets[2][0])) {
                        $type .= '[]'; // regardless of the number of bracket pairs, just a single pair is taken
                    }
				    if (isset($matchWithOffsets[3])) {
					    $name = $matchWithOffsets[3][0];
					    $plcHld = new SqlPatternPlaceholder($offset, $name, $type);
					    if (!isset($namedPlaceholderMap[$name])) {
						    $namedPlaceholderMap[$name] = [];
					    }
					    $namedPlaceholderMap[$name][] = $plcHld;
				    }
				    else {
					    $plcHld = new SqlPatternPlaceholder($offset, count($positionalPlaceholders), $type);
					    $positionalPlaceholders[] = $plcHld;
				    }
				    $rawOffsetDelta -= strlen($matchWithOffsets[0][0]); // put no characters instead of the whole match
				    return '';
			    }
		    },
		    $sqlPattern
	    );

	    return new SqlPattern($rawSql, $positionalPlaceholders, $namedPlaceholderMap);
    }
}
