<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\NamedDbObject;
use Ivory\Type\IType;

class DomainType implements IType
{
    use NamedDbObject;

    private $baseType;

    public function __construct(string $schemaName, string $typeName, IType $baseType)
    {
        $this->setName($schemaName, $typeName);
        $this->baseType = $baseType;
    }

    public function parseValue(string $extRepr)
    {
        return $this->baseType->parseValue($extRepr);
    }

    public function serializeValue($val): string
    {
        return $this->baseType->serializeValue($val);
    }
}
