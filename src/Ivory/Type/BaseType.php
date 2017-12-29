<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\NamedDbObject;

abstract class BaseType implements IType
{
    use NamedDbObject;

    public function __construct(string $schemaName, string $name)
    {
        $this->setName($schemaName, $name);
    }

    protected function invalidValueException($val, ?\Exception $cause = null): \InvalidArgumentException
    {
        $message = "Value '$val' is not valid for type {$this->schemaName}.{$this->name}";
        return new \InvalidArgumentException($message, 0, $cause);
    }
}
