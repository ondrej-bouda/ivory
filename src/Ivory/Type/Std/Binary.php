<?php
namespace Ivory\Type\Std;

/**
 * Binary data.
 *
 * Represented as the PHP <tt>string</tt> type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-binary.html
 */
class Binary extends \Ivory\Type\BaseType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}

		// depending on the PostgreSQL bytea_output configuration parameter, data may be encoded either in the "hex" or
		// "escape" format, which may be recognized by the '\x' prefix
		if (substr($str, 0, 2) == '\\x') {
			return hex2bin(substr($str, 2));
		}
		else {
			return pg_unescape_bytea($str);
		}
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}
		else {
			return "E'\\\\x" . bin2hex($val) . "'";
		}
	}
}
