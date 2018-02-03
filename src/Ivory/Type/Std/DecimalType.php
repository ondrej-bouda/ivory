<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Decimal;

/**
 * Arbitrary precision decimal number type.
 *
 * Represented as a {@link \Ivory\Value\Decimal} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class DecimalType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        if (strcasecmp($extRepr, 'NaN') == 0) {
            return Decimal::NaN();
        }

        return Decimal::fromNumber($extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Decimal) {
            $val = Decimal::fromNumber($val);
        }

        if ($val->isNaN()) {
            return "'NaN'";
        } else {
            return $val->toString();
        }
    }
}
