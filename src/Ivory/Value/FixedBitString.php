<?php
namespace Ivory\Value;

/**
 * Fixed-length bit string - a string of 1's and 0's.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 * The representation and operations resemble the specification for bit string types in PostgreSQL.
 *
 * It is possible to access individual bits using the array indices (readonly). The leftmost bit is at offset 0. Testing
 * whether the bit string has a bit at a given offset may be performed using <tt>isset($this[$offset])</tt>. Note that,
 * apart from reading out of such a call, it does not test whether the given bit is <em>set</em> (i.e., whether it
 * is 1) - it merely tests whether it is legal to access it.
 *
 * @see http://www.postgresql.org/docs/current/static/datatype-bit.html PostgreSQL Bit String Types
 * @see http://www.postgresql.org/docs/current/static/functions-bitstring.html PostgreSQL Bit String Functions and Operators
 */
class FixedBitString extends BitString
{
	/**
	 * @param string $bits the bit string, i.e., a string of 1's and 0's
	 * @param int|null $length length of the bit string in bits;
	 *                         <tt>null</tt> for taking the length of <tt>$bits</tt>;
	 *                         if less or greater than <tt>strlen($bits)</tt>, the bits get zero-padded or truncated on
	 *                           the right to be exactly <tt>$length</tt> bits and a warning is issued
	 * @return FixedBitString
	 * @throws \InvalidArgumentException if <tt>$length</tt> is a non-positive number (PostgreSQL forbids it)
	 */
	public static function fromString($bits, $length = null)
	{
		$bits = (string)$bits;
		$bitsLen = strlen($bits);

		if ($length === null) {
			return new FixedBitString($bits, $bitsLen);
		}
		elseif ($length <= 0) {
			throw new \InvalidArgumentException('length is non-positive');
		}
		elseif ($length == $bitsLen) {
			return new FixedBitString($bits, $bitsLen);
		}
		elseif ($length > $bitsLen) {
			return new FixedBitString(str_pad($bits, $length, '0'), $length);
		}
		else { // $length < $bitsLen
			trigger_error("Bit string truncated to the length of $length", E_USER_WARNING);
			return new FixedBitString(substr($bits, 0, $length), $length);
		}
	}


	public function substr($offset, $length = null)
	{
		return new FixedBitString(substr($this->bits, $offset, $length));
	}
}
