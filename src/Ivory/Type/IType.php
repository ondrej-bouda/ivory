<?php
namespace Ivory\Type;

/**
 * A data type object representing a PostgreSQL type.
 */
interface IType
{
	/**
	 * Parses a value of the represented type from its external representation.
	 *
	 * The external representation is given by the output function of the PostgreSQL type this object represents.
	 * In case `null` is given, `null` is returned.
	 *
	 * @param string|null $str
	 * @return mixed the value parsed from <tt>$str</tt>, or <tt>null</tt> if <tt>$str</tt> is <tt>null</tt>
	 */
	function parseValue($str);

	/**
	 * Serializes a value of the represented type to a string to be pasted in the SQL query.
	 *
	 * In case `null` is given, the `'NULL'` string is returned.
	 *
	 * @param mixed $val
	 * @return string
	 */
	function serializeValue($val);
}
