<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\IType;
use Ivory\Type\TypeBase;

class DomainType extends TypeBase implements IType
{
    private $baseType;

    public function __construct(string $schemaName, string $typeName, IType $baseType)
    {
        parent::__construct($schemaName, $typeName);
        $this->baseType = $baseType;
    }

    public function parseValue(string $extRepr)
    {
        return $this->baseType->parseValue($extRepr);
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        $baseExpr = $this->baseType->serializeValue($val, false);
        return $this->typeCastExpr($forceType, $baseExpr);
    }
}
