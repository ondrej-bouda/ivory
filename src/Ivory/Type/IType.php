<?php
namespace Ivory\Type;

/**
 * PostgreSQL data type.
 */
interface IType extends IValueSerializer, \Ivory\INamedDbObject
{
    /**
     * Parses a value of the represented type from its external representation.
     *
     * The external representation is given by the output function of the PostgreSQL type this object represents.
     * In case `null` is given, `null` is returned.
     *
     * @todo for performance reasons, extend with optional arguments $offset and $len, limiting the part of the string to consider; trimming could thus be implemented as just shifting these positions
     * @param string|null $str
     * @return mixed the value parsed from <tt>$str</tt>, or <tt>null</tt> if <tt>$str</tt> is <tt>null</tt>
     */
    function parseValue($str);
}
