<?php
namespace Ivory\Type;

/**
 * Converter between PostgreSQL and PHP values of the corresponding types.
 *
 * The converter must be stateless as it may be used repetitively, even for distinct PostgreSQL types.
 */
interface IType
{
	/**
	 * Parses a value of the represented type from its external representation.
	 *
	 * The external representation is given by the output function of the PostgreSQL type this object represents.
	 * In case `null` is given, `null` is returned.
	 *
	 * @todo for performance reasons, extend with optional arguments $offset and $len, limiting the part of the string to consider; trimming could thus be implemented as just shifting these positions
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
