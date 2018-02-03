<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\IComparable;

class EnumItem implements IComparable
{
    private $typeSchema;
    private $typeName;
    private $value;
    private $ordinal;

    public static function forType(string $typeSchema, string $typeName, string $value, ?int $ordinal = null): EnumItem
    {
        return new EnumItem($typeSchema, $typeName, $value, $ordinal);
    }

    private function __construct(string $typeSchema, string $typeName, string $value, ?int $ordinal)
    {
        $this->typeSchema = $typeSchema;
        $this->typeName = $typeName;
        $this->value = $value;
        $this->ordinal = $ordinal;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getTypeSchema(): string
    {
        return $this->typeSchema;
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function __toString()
    {
        return $this->value;
    }

    public function equals($other): bool
    {
        return (
            $other instanceof EnumItem &&
            $this->typeSchema == $other->typeSchema &&
            $this->typeName == $other->typeName &&
            $this->value == $other->value
        );
    }

    public function compareTo($other): int
    {
        if (!$other instanceof EnumItem) {
            throw new IncomparableException();
        }
        if ($this->typeSchema != $other->typeSchema || $this->typeName != $other->typeName) {
            throw new IncomparableException('Comparing enum items of different enumerations.');
        }

        if ($this->value == $other->value) {
            return 0;
        }
        if ($this->ordinal === null || $other->ordinal === null) {
            throw new IncomparableException('Unspecified ordinal');
        }
        return ($this->ordinal - $other->ordinal);
    }
}
