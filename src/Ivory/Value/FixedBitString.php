<?php
namespace Ivory\Value;

use Ivory\Exception\NotImplementedException;

/**
 * Fixed-length bit string - a string of 1's and 0's.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 * The representation and operations resemble the specification of the `BIT` type in PostgreSQL.
 *
 * It is possible to access individual bits using the array indices (readonly). The leftmost bit is at offset 0. Testing
 * whether the bit string has a bit at a given offset may be performed using `isset($this[$offset])`. Note that, apart
 * from reading out of such a call, it does not test whether the given bit is *set* (i.e., whether it is 1) - it merely
 * tests whether it is legal to access it.
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
	 *                           the right to be exactly <tt>$length</tt> bits (and a warning is issued is case of
	 *                           truncation)
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

	/**
	 * Creates a bit string as the two's complement of a given integer represented on a given number of bits.
	 *
	 * The least significant bit is the rightmost one in the resulting bit string.
	 *
	 * Overflows are not detected - if `$length` is not sufficient for representing the whole `$int`, only the `$length`
	 * least significant bits of the representation are used quietly, without any notice.
	 *
	 * @param int $int integer to represent
	 * @param int $length number of bits
	 * @return FixedBitString
	 * @throws \InvalidArgumentException if <tt>$length</tt> is a non-positive number
	 */
	public static function fromInt($int, $length)
	{
		if ($length <= 0) {
			throw new \InvalidArgumentException('length <= 0');
		}

		$bin = decbin($int);

		$padBit = ($int >= 0 ? '0' : '1');
		$padded = str_pad($bin, $length, $padBit, STR_PAD_LEFT);

		$truncated = substr($padded, -$length);
		return self::fromString($truncated);
	}

	/**
	 * Returns a non-negative integer encoded by the bits in the bit string.
	 *
	 * The standard binary encoding is used. The rightmost bit in the string is the least significant in the integer.
	 *
	 * Only that many rightmost bits are taken which allow the encoded integer be represented correctly by the PHP `int`
	 * type. That is 31 bits or 63 bits depending on whether this is a 32-bit or 64-bit compilation of PHP.
	 *
	 * For getting an arbitrary-length integer instead of the truncated `int`, use {@link toNumber()}.
	 *
	 * Note that, unlike {@link fromInt()}, this method does NOT work with the two's complement, but rather with the
	 * standard binary encoding, and thus never returns any negative number. This is to resemble the PostgreSQL
	 * behaviour.
	 *
	 * @return int
	 */
	public function toInt()
	{
		return bindec(substr($this->bits, -(PHP_INT_SIZE * 8 - 1)));
	}

	public static function fromNumber($number)
	{
		// TODO: make up an object of the class for representing arbitrary-length integers, and fix the interface (method name) and phpdoc
		throw new NotImplementedException();
	}

	/**
	 * Returns a non-negative arbitrary-length integer encoded by the bits in the bit string.
	 *
	 * The standard binary encoding is used. The rightmost bit in the string is the least significant in the integer.
	 *
	 * Contrary to {@link toInt()}, this method uses all the bits to constitute the result.
	 *
	 * @return object
	 */
	public function toNumber()
	{
		// TODO: make up an object of the class for representing arbitrary-length integers, and fix the interface (method name) and phpdoc
		throw new NotImplementedException();
	}
}
