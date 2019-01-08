<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Decimal;

/**
 * Arbitrary precision decimal number type.
 *
 * Represented as a {@link \Ivory\Value\Decimal} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-numeric.html
 */
class DecimalType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        if (strcasecmp($extRepr, 'NaN') == 0) {
            return Decimal::NaN();
        }

        return Decimal::fromNumber($extRepr);
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof Decimal) {
            $val = Decimal::fromNumber($val);
        }

        if ($val->isNaN()) {
            return $this->indicateType($forceType, "'NaN'");
        } else {
            return $this->typeCastExpr($forceType, $val->toString());
        }
    }
}
