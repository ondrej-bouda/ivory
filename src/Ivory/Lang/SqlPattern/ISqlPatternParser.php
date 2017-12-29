<?php
declare(strict_types=1);
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
