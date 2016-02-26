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
class BigIntType extends IntegerType implements IDiscreteType
{
	const _PHP_INT_MIN = (PHP_MAJOR_VERSION >= 7 ? PHP_INT_MIN : -PHP_INT_MAX); // PHP 7: refactor out

	public function parseValue($str)
	{
		if ($str > PHP_INT_MAX || $str < self::_PHP_INT_MIN) { // correctness: int does not overflow, but rather gets converted to a float
			return $str;
		}
		else {
			return parent::parseValue($str);
		}
	}

	public function serializeValue($val)
	{
		if ($val > PHP_INT_MAX || $val < self::_PHP_INT_MIN) {
			if (preg_match('~^\s*-?[0-9]+\s*$~', $val)) {
				return (string)$val;
			}
			else {
				$this->throwInvalidValue($val);
			}
		}
		else {
			return parent::serializeValue($val);
		}
	}

	public function compareValues($a, $b)
	{
		if ($a === null || $b === null) {
			return null;
		}
		if ($a > PHP_INT_MAX || $b > PHP_INT_MAX || $a < self::_PHP_INT_MIN || $b < self::_PHP_INT_MIN) {
			return bccomp($a, $b, 0);
		}
		else {
			return (int)$a - (int)$b;
		}
	}

	public function step($delta, $value)
	{
		if ($value === null) {
			return null;
		}
		if ($value > PHP_INT_MAX || $value < self::_PHP_INT_MIN) {
			return bcadd($value, $delta, 0);
		}
		else {
			return (int)$value + $delta;
		}
	}
}
