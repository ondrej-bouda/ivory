<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;

/**
 * A special class used for any PostgreSQL type which is not recognized.
 *
 * Objects of this class only serve as null-objects. Any operation on `UndefinedType` objects, even with `null` values,
 * results in an {@link \Ivory\Exception\UndefinedTypeException} being thrown.
 */
class UndefinedType extends BaseType
{
    private $connName;

    /**
     * @param string $schemaName name of database schema of data type instead of which this object is to be created
     * @param string $name name of data type instead of which this object is to be created
     * @param string $connName name of Ivory connection for which the object is to be created (for reporting purposes)
     */
    public function __construct(string $schemaName, string $name, string $connName)
    {
        parent::__construct($schemaName, $name);
        $this->connName = $connName;
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
