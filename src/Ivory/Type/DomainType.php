<?php
namespace Ivory\Type;

use Ivory\NamedDbObject;

class DomainType implements INamedType
{
    use NamedDbObject;

    private $baseType;

    public function __construct($schemaName, $typeName, IType $baseType)
    {
        $this->setName($schemaName, $typeName);
        $this->baseType = $baseType;
    }

    public function parseValue($str)
    {
        return $this->baseType->parseValue($str);
    }

    public function serializeValue($val)
    {
        return $this->baseType->serializeValue($val);
    }
}
