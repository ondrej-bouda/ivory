<?php
namespace Ivory\Type\Std;

/**
 * Signed integer.
 *
 * Represented as the PHP <tt>int</tt> type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-numeric.html#DATATYPE-INT
 */
class Integer extends \Ivory\Type\BaseType
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
}