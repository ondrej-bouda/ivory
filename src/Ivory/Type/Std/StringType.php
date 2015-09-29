<?php
namespace Ivory\Type\Std;

/**
 * Character string.
 *
 * Represented as the PHP `string` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-character.html
 */
class StringType extends \Ivory\Type\BaseType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}
		else {
			return $str;
		}
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		else {
			return "'" . strtr($val, ["'" => "''"]) . "'";
		}
	}
}
