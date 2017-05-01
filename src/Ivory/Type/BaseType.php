<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\NamedDbObject;

abstract class BaseType implements IType
{
    use NamedDbObject;

    public function __construct(string $schemaName, string $name)
    {
        $this->setName($schemaName, $name);
    }

    protected function throwInvalidValue($str, \Exception $cause = null)
    {
        $message = "Value '$str' is not valid for type {$this->schemaName}.{$this->name}";
        throw new \InvalidArgumentException($message, 0, $cause);
    }
}
