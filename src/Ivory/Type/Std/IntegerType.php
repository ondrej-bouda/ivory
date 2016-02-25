<?php
namespace Ivory\Type\Std;

use Ivory\Type\IDiscreteType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Signed integer.
 *
 * Represented as the PHP `int` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html#DATATYPE-INT
 */
class IntegerType extends \Ivory\Type\BaseType implements ITotallyOrderedType, IDiscreteType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}
		else {
			return (int)$str;
		}
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		else {
			return (int)$val;
		}
	}

	public function compareValues($a, $b)
	{
		if ($a === null || $b === null) {
			return null;
		}
		return (int)$a - (int)$b;
	}

	public function step($delta, $value)
	{
		return ($value === null ? null : $value + $delta);
	}
}
