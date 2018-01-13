<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Exception\IncomparableException;
use Ivory\Exception\InternalException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * A polymorphic type, which basically cannot be retrieved in a `SELECT` result, except for `NULL` values.
 */
class PolymorphicPseudoType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        throw new InternalException('A non-null value to be parsed for a polymorphic pseudo-type');
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } else {
            throw $this->invalidValueException($val);
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        throw new IncomparableException('Non-null values to be compared according to ' . PolymorphicPseudoType::class);
    }
}
