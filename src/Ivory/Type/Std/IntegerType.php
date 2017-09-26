<?php
namespace Ivory\Type\Std;

use Ivory\Type\IDiscreteType;

/**
 * Signed integer.
 *
 * Represented as the PHP `int` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html#DATATYPE-INT
 */
class IntegerType extends \Ivory\Type\BaseType implements IDiscreteType
{
    public function parseValue(string $str)
    {
        return (int)$str;
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } else {
            return (int)$val;
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        return $a <=> $b;
    }

    public function step(int $delta, $value)
    {
        return ($value === null ? null : $value + $delta);
    }
}
