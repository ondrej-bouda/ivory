<?php
namespace Ivory\Type\Postgresql;

use Ivory\Exception\UnsupportedException;
use Ivory\NamedDbObject;
use Ivory\Type\IDiscreteType;
use Ivory\Type\IType;
use Ivory\Type\ITotallyOrderedType;

class DomainType implements IDiscreteType
{
    use NamedDbObject;

    private $baseType;

    public function __construct(string $schemaName, string $typeName, IType $baseType)
    {
        $this->setName($schemaName, $typeName);
        $this->baseType = $baseType;
    }

    public function parseValue(string $str)
    {
        return $this->baseType->parseValue($str);
    }

    public function serializeValue($val): string
    {
        return $this->baseType->serializeValue($val);
    }

    public function compareValues($a, $b): ?int
    {
        if ($this->baseType instanceof ITotallyOrderedType) {
            return $this->baseType->compareValues($a, $b);
        } else {
            $msg = "The domain {$this->getSchemaName()}.{$this->getName()} base type is not totally ordered";
            throw new UnsupportedException($msg);
        }
    }

    public function step(int $delta, $value)
    {
        if ($this->baseType instanceof IDiscreteType) {
            return $this->baseType->step($delta, $value);
        } else {
            $msg = "The domain {$this->getSchemaName()}.{$this->getName()} base type is not discrete";
            throw new UnsupportedException($msg);
        }
    }
}
