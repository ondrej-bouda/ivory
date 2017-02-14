<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\NamedDbObject;

abstract class BaseType implements INamedType
{
    use NamedDbObject;

    private $connection;

    public function __construct(string $schemaName, string $name, IConnection $connection)
    {
        $this->setName($schemaName, $name);
        $this->connection = $connection;
    }

    final protected function getConnection()
    {
        return $this->connection;
    }

    protected function throwInvalidValue($str, \Exception $cause = null)
    {
        $message = "Value '$str' is not valid for type {$this->schemaName}.{$this->name}";
        throw new \InvalidArgumentException($message, 0, $cause);
    }
}
