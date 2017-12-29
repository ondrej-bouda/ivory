<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Inexact, variable-precision numeric type.
 *
 * Represented as the PHP `float` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class FloatType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $str)
    {
        if (is_numeric($str)) {
            return (float)$str;
        } elseif (strcasecmp($str, 'NaN') == 0) {
            return NAN;
        } elseif (strcasecmp($str, 'Infinity') == 0) {
            return INF;
        } elseif (strcasecmp($str, '-Infinity') == 0) {
            return -INF;
        } else {
            throw $this->invalidValueException($str);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif (is_finite($val)) {
            return (string)$val;
        } elseif (is_nan($val)) {
            return "'NaN'";
        } elseif ($val < 0) {
            return "'-Infinity'";
        } else {
            return "'Infinity'";
        }
    }

    public function compareValues($a, $b): ?int
    {
        return self::compareFloats($a, $b);
    }

    public static function compareFloats($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }

        if ($a === NAN) {
            return ($b === NAN ? 0 : 1);
        } elseif ($b === NAN) {
            return -1;
        }

        return (float)$a <=> (float)$b;
    }
}
