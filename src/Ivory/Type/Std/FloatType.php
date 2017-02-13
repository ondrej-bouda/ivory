<?php
namespace Ivory\Type\Std;

use Ivory\Type\ITotallyOrderedType;

/**
 * Inexact, variable-precision numeric type.
 *
 * Represented as the PHP `float` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class FloatType extends \Ivory\Type\BaseType implements ITotallyOrderedType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } elseif (is_numeric($str)) {
            return (float)$str;
        } elseif (strcasecmp($str, 'NaN') == 0) {
            return NAN;
        } elseif (strcasecmp($str, 'Infinity') == 0) {
            return INF;
        } elseif (strcasecmp($str, '-Infinity') == 0) {
            return -INF;
        } else {
            $this->throwInvalidValue($str);
        }
    }

    public function serializeValue($val)
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

    public function compareValues($a, $b)
    {
        return self::compareFloats($a, $b);
    }

    public static function compareFloats($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }

        if ($a === NAN) {
            return ($b === NAN ? 0 : 1);
        } elseif ($b === NAN) {
            return -1;
        }

        // TODO PHP 7: use the spaceship operator
        if ((float)$a < (float)$b) {
            return -1;
        } elseif ((float)$a == (float)$b) {
            return 0;
        } else {
            return 1;
        }
    }
}
