<?php
namespace Ivory\Type\Std;

use Ivory\Type\IDiscreteType;

/**
 * Signed eight-byte integer.
 *
 * The PHP `int` representation is preferred. If, however, the value overflows `int` size, a string is returned
 * containing the decimal number.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html#DATATYPE-INT
 */
class BigIntSafeType extends IntegerType implements IDiscreteType
{
    public static function createForRange($min, $max, $schemaName, $typeName)
    {
        if (bccomp($min, PHP_INT_MIN) >= 0 && bccomp($max, PHP_INT_MAX) <= 0) {
            return new IntegerType($schemaName, $typeName);
        } else {
            return new BigIntSafeType($schemaName, $typeName);
        }
    }

    public function parseValue($str)
    {
        if ($str > PHP_INT_MAX || $str < PHP_INT_MIN) { // correctness: int does not overflow, but rather gets converted to a float
            return $str;
        } else {
            return parent::parseValue($str);
        }
    }

    public function serializeValue($val): string
    {
        if ($val > PHP_INT_MAX || $val < PHP_INT_MIN) {
            if (preg_match('~^\s*-?[0-9]+\s*$~', $val)) {
                return (string)$val;
            } else {
                $this->throwInvalidValue($val);
            }
        } else {
            return parent::serializeValue($val);
        }
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }
        if ($a > PHP_INT_MAX || $b > PHP_INT_MAX || $a < PHP_INT_MIN || $b < PHP_INT_MIN) {
            return bccomp($a, $b, 0);
        } else {
            return (int)$a - (int)$b;
        }
    }

    public function step(int $delta, $value)
    {
        if ($value === null) {
            return null;
        }
        if ($value > PHP_INT_MAX || $value < PHP_INT_MIN) {
            return bcadd($value, $delta, 0);
        } else {
            return (int)$value + $delta;
        }
    }
}
