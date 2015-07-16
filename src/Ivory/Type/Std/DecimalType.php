<?php
namespace Ivory\Type\Std;

use Ivory\Value\Decimal;

/**
 * Arbitrary precision decimal number type.
 *
 * Represented as a {@link \Ivory\Value\Decimal} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class DecimalType extends \Ivory\Type\BaseType
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
		elseif ($val instanceof Decimal) {
			if ($val->isNaN()) {
				return "'NaN'";
			}
			else {
				return $val->toString();
			}
		}
		else {
			$this->throwInvalidValue($val);
		}
	}
}
