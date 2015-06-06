<?php
namespace Ivory\Type\Std;

/**
 * Logical boolean (true/false).
 *
 * Represented as the PHP <tt>bool</tt> type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-boolean.html
 */
class Boolean extends \Ivory\Type\BaseType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}

		switch (strtoupper(trim($str))) {
			case 'T':
			case 'TRUE':
			case 'Y':
			case 'YES':
			case 'ON':
			case '1':
				return true;

			case 'F':
			case 'FALSE':
			case 'N':
			case 'NO':
			case 'OFF':
			case '0':
				return false;

			default:
				$this->throwInvalidValue($str);
		}
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		elseif ($val) {
			return 'TRUE';
		}
		else {
			return 'FALSE';
		}
	}
}
