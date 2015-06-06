<?php
namespace Ivory\Type\Std;

/**
 * Base for bit string type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-bit.html
 */
abstract class BitString extends \Ivory\Type\BaseType
{
	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		elseif ($val instanceof \Ivory\Value\BitString) {
			return "B'" . $val->toString() . "'";
		}
		else {
			$this->throwInvalidValue($val);
		}
	}
}
