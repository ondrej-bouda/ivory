<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;

/**
 * Inexact, variable-precision numeric type.
 *
 * Represented as the PHP `float` type.
 *
 * @see https://www.postgresql.org/docs/11/datatype-numeric.html
 */
class FloatType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        if (is_numeric($extRepr)) {
            return (float)$extRepr;
        } elseif (strcasecmp($extRepr, 'NaN') == 0) {
            return NAN;
        } elseif (strcasecmp($extRepr, 'Infinity') == 0) {
            return INF;
        } elseif (strcasecmp($extRepr, '-Infinity') == 0) {
            return -INF;
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif (is_finite($val)) {
            return $this->typeCastExpr($strictType, (string)(float)$val);
        } elseif (is_nan($val)) {
            return $this->indicateType($strictType, "'NaN'");
        } elseif ($val < 0) {
            return $this->indicateType($strictType, "'-Infinity'");
        } else {
            return $this->indicateType($strictType, "'Infinity'");
        }
    }
}
