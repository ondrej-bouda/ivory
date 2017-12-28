<?php
namespace Ivory\Type;

use Ivory\INamedDbObject;

/**
 * PostgreSQL data type.
 */
interface IType extends IValueSerializer, INamedDbObject
{
    /**
     * Parses a value of the represented type from its external representation.
     *
     * The external representation is given by the output function of the PostgreSQL type this object represents.
     * Note that `null` values may not be given to this method - they have to be processed separately.
     *
     * @param string $str
     * @return mixed the value parsed from <tt>$str</tt>
     */
    function parseValue(string $str);
}
