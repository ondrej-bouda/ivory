<?php
namespace Ivory\Type\Std;

use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Decimal;

/**
 * Arbitrary precision decimal number type.
 *
 * Represented as a {@link \Ivory\Value\Decimal} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class DecimalType extends \Ivory\Type\BaseType implements ITotallyOrderedType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}

		if (strcasecmp($str, 'NaN') == 0) {
			return Decimal::NaN();
		}

		return Decimal::fromNumber($str);
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}

		if (!$val instanceof Decimal) {
            $val = Decimal::fromNumber($val);
        }

        if ($val->isNaN()) {
            return "'NaN'";
        }
        else {
            return $val->toString();
		}
	}

	public function compareValues($a, $b)
	{
		if ($a === null || $b === null) {
			return null;
		}
		if (!$a instanceof Decimal) {
			$a = Decimal::fromNumber($a);
		}
		return $a->compareTo($b);
	}
}
