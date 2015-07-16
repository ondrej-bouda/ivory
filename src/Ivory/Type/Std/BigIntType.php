<?php
namespace Ivory\Type\Std;

/**
 * Signed eight-byte integer.
 *
 * The PHP <tt>int</tt> representation is preferred. If, however, the value overflows <tt>int</tt> size, a string is
 * returned containing the decimal number.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html#DATATYPE-INT
 */
class BigIntType extends IntegerType
{
	public function parseValue($str)
	{
		if ($str > PHP_INT_MAX || $str < -PHP_INT_MAX) { // NOTE: correct - int does not overflow, but rather gets converted to a float
			return $str;
		}
		else {
			return parent::parseValue($str);
		}
	}

	public function serializeValue($val)
	{
		if ($val > PHP_INT_MAX || $val < -PHP_INT_MAX) {
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
}
