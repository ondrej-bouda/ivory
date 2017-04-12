<?php
namespace Ivory\Lang\SqlPattern;

interface ISqlPatternParser
{
    /**
     * Parses an SQL pattern string to an {@link SqlPattern} object.
     *
     * @param string $sqlPatternString
     * @return SqlPattern
     */
    function parse(string $sqlPatternString): SqlPattern;
}
