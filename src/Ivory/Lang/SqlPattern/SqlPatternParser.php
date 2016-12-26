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
	    	      ( [[:alpha:]_] [[:alnum:]_]* )?           #   optional type name, starting with a letter or underscore
	    	      (?: : ( [[:alpha:]_] [[:alnum:]_]* ) )?   #   optional parameter name
	    	    | ( % )                                     # or another percent sign -> literal %
	    	  )
	    	 ~x',
		    function ($matchWithOffsets) use (&$positionalPlaceholders, &$namedPlaceholderMap, &$rawOffsetDelta) {
			    if (isset($matchWithOffsets[3])) {
				    $rawOffsetDelta--; // put one character instead of two
				    return '%';
			    }
			    else {
			    	$offset = $matchWithOffsets[0][1] + $rawOffsetDelta;
				    $type = (!empty($matchWithOffsets[1][0]) ? $matchWithOffsets[1][0] : null); // correctness: empty() is OK as the type name may not be "0"
				    if (isset($matchWithOffsets[2])) {
					    $name = $matchWithOffsets[2][0];
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
