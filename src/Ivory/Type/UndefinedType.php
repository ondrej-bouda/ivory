<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

/**
 * A special class used for any PostgreSQL type which is not recognized.
 *
 * Objects of this class only serve as null-objects. Any operation on `UndefinedType` objects, even with `null` values,
 * results in an {@link \Ivory\Exception\UndefinedTypeException} being thrown.
 */
class UndefinedType extends BaseType
{
    public function parseValue($str)
    {
        throw new UndefinedTypeException(
            "{$this->getSchemaName()}.{$this->getName()} on connection {$this->getConnection()->getName()}"
        );
    }

    public function serializeValue($val)
    {
        throw new UndefinedTypeException(
            "{$this->getSchemaName()}.{$this->getName()} on connection {$this->getConnection()->getName()}"
        );
    }
}
