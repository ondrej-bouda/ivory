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
	 * In case <tt>null</tt> is given, <tt>null</tt> is returned.
	 *
	 * @param string|null $str
	 * @return mixed the value parsed from <tt>$str</tt>, or <tt>null</tt> if <tt>$str</tt> is <tt>null</tt>
	 */
	function parseValue($str);

	/**
	 * Serializes a value of the represented type to a string to be pasted in the SQL query.
	 *
	 * In case <tt>null</tt> is given, the <tt>'NULL'</tt> string is returned.
	 *
	 * @param mixed $val
	 * @return string
	 */
	function serializeValue($val);
}
