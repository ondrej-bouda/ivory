<?php
namespace Ivory\Type\Std;

/**
 * Inexact, variable-precision numeric type.
 *
 * Represented as the PHP `float` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html
 */
class FloatType extends \Ivory\Type\BaseType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}
		elseif (is_numeric($str)) {
			return (float)$str;
		}
		elseif (strcasecmp($str, 'NaN') == 0) {
			return NAN;
		}
		elseif (strcasecmp($str, 'Infinity') == 0) {
			return INF;
		}
		elseif (strcasecmp($str, '-Infinity') == 0) {
			return -INF;
		}
		else {
			$this->throwInvalidValue($str);
		}
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		elseif (is_finite($val)) {
			return (string)$val;
		}
		elseif (is_nan($val)) {
			return "'NaN'";
		}
		elseif ($val < 0) {
			return "'-Infinity'";
		}
		else {
			return "'Infinity'";
		}
	}
}
