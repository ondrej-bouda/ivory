<?php
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Type\ConnectionDependentBaseType;

/**
 * A special class used for any PostgreSQL type which is not recognized.
 *
 * Objects of this class only serve as null-objects. Any operation on `UndefinedType` objects, even with `null` values,
 * results in an {@link \Ivory\Exception\UndefinedTypeException} being thrown.
 */
class UndefinedType extends ConnectionDependentBaseType
{
    private $connName;

    public function attachToConnection(IConnection $connection)
    {
        $this->connName = $connection->getName();
    }

    public function detachFromConnection()
    {
        $this->connName = null;
    }

    public function parseValue($str)
    {
        throw new UndefinedTypeException("{$this->getSchemaName()}.{$this->getName()} on connection {$this->connName}");
    }

    public function serializeValue($val): string
    {
        throw new UndefinedTypeException("{$this->getSchemaName()}.{$this->getName()} on connection {$this->connName}");
    }
}