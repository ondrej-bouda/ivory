<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\INamedDbObject;

/**
 * PostgreSQL data type.
 */
interface IType extends IValueSerializer, INamedDbObject
{
    /**
     * Parses a value from its PostgreSQL external representation.
     *
     * The external representation is given by the output function of the PostgreSQL type this type object represents.
     * Note that `null` values are never given to this method - they are processed separately, always producing `null`.
     *
     * @param string $extRepr external representation of a value
     * @return mixed the value represented by <tt>$str</tt>
     */
    function parseValue(string $extRepr);
}
