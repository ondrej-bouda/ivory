<?php
namespace Ivory\Value;

use Ivory\ImmutableException;

/**
 * A common super type for bit string types - strings of 1's and 0's.
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
 *
 * @todo optimize the internal representation (unpack('c*', $value) might be handy for constructing from string)
 */
abstract class BitString implements \ArrayAccess
{
	protected $bits;
	protected $len;


	/**
	 * @param string $bits
	 */
	protected function __construct($bits)
	{
		if (!preg_match('~^[01]*$~', $bits)) {
			throw new \InvalidArgumentException('bits');
		}

		$this->bits = $bits;
		$this->len = strlen($bits);
	}


	/**
	 * @return string textual representation of this bit string (index 0 is the leftmost bit): a string of 1's and 0's
	 */
	public function toString()
	{
		return $this->bits;
	}

	/**
	 * Returns a non-negative integer encoded by the bits in the bit string.
	 *
	 * The standard binary encoding is used. The rightmost bit in the string is the least significant in the integer.
	 *
	 * Only that many rightmost bits are taken which allow the encoded integer be represented correctly by the PHP
	 * <tt>int</tt> type. That is 31 bits or 63 bits depending on whether this is a 32-bit or 64-bit compilation of PHP.
	 *
	 * For getting an arbitrary-length integer instead of the truncated <tt>int</tt>, use {@link toNumber()}.
	 *
	 * @return int
	 */
	public function toInt()
	{
		// TODO
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
	}

	/**
	 * Returns an array of octets made up from the bits of this bit string.
	 *
	 * The first octet is made from the 8 rightmost bits, the second octet from the next 8 bits, etc.
	 * Each octet is represented by an integer. The standard binary encoding is used, i.e., the rightmost bit in the
	 * substring is the least significant bit in the integer.
	 *
	 * E.g., bit string <tt>10011001111001</tt> results in <tt>[0b1111001, 0b100110]</tt>, which is <tt>[121, 38]</tt>.
	 *
	 * @return int[]
	 */
	public function toOctetArray()
	{
		// TODO
	}

	/**
	 * @return int number of bits in the bit string
	 */
	public function getLength()
	{
		return $this->len;
	}

	/**
	 * @return bool whether all the bits are zero
	 */
	public function isZero()
	{
		return !$this->isNonZero();
	}

	/**
	 * @return bool whether some bit is one
	 */
	public function isNonZero()
	{
		for ($i = 0; $i < $this->len; $i++) {
			if ($this->bits[$i] == '1') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param BitString $other any bit string of any length
	 * @return bool whether this and the other bit string have the same bits, i.e., are the same 1's and 0's strings
	 */
	public function bitEquals(BitString $other)
	{

	}

	/**
	 * @param BitString $other any bit string of any length
	 * @return bool whether this and the other bit string share at least one set bit at the same offset
	 */
	public function intersects(BitString $other)
	{

	}

	/**
	 * Extracts a bit substring from this bit string.
	 *
	 * The arguments are the same as for PHP {@link substr()} function, except that this method works on a string of 1's
	 * and 0's.
	 *
	 * @param int $offset
	 * @param int|null $length
	 * @return static the substring of the bit string
	 */
	abstract public function substr($offset, $length = null);

	/**
	 * Concatenates this bit string with another one.
	 *
	 * The result is a bit string with bits of this bit string followed by the bits from the <tt>$concatenated</tt> bit
	 * string.
	 *
	 * If both the operands are fixed-length of length <tt>A</tt> and <tt>B</tt>, the result is a fixed-length bit
	 * string of length <tt>A+B</tt>. Otherwise, the result is a variable-length bit string.
	 *
	 * @param BitString $concatenated
	 * @return BitString bit string made up by concatenating the <tt>$concatenated</tt> after this bit string
	 */
	abstract public function concat(BitString $concatenated);

	/**
	 * Bitwise AND operation. Only legal for operands of equal bit lengths.
	 *
	 * @param BitString $other the other operand
	 * @return BitString new bit string: <tt>$this & $other</tt>
	 */
	abstract public function bitAnd(BitString $other);

	/**
	 * Bitwise OR operation. Only legal for operands of equal bit lengths.
	 *
	 * @param BitString $other the other operand
	 * @return BitString new bit string: <tt>$this | $other</tt>
	 */
	abstract public function bitOr(BitString $other);

	/**
	 * Bitwise exclusive OR operation. Only legal for operands of equal bit lengths.
	 *
	 * @param BitString $other the other operand
	 * @return BitString new bit string: <tt>$this ^ $other</tt>
	 */
	abstract public function bitXor(BitString $other);

	/**
	 * Bitwise negation, i.e., reverses all bits of the bit string.
	 *
	 * @return BitString new bit string: <tt>~$this</tt>
	 */
	abstract public function bitNot();

	/**
	 * Shifts the bits to the left.
	 *
	 * The length of the bit string is preserved, thus, the <tt>$shift</tt> trailing bits are discarded. The shifted bit
	 * positions are filled with 0's.
	 *
	 * @param int $shift number of positions to shift the bits to the left;
	 *                   might even be negative (results in shifting to the right by <tt>-$shift</tt>)
	 * @return BitString a bit string with bits shifted by <tt>$shift</tt> to the left
	 */
	public function bitShiftLeft($shift)
	{

	}

	/**
	 * Shifts the bits to the right.
	 *
	 * The length of the bit string is preserved, thus, the <tt>$shift</tt> trailing bits are discarded. The shifted bit
	 * positions are filled with 0's.
	 *
	 * @param int $shift number of positions to shift the bits to the right;
	 *                   might even be negative (results in shifting to the left by <tt>-$shift</tt>)
	 * @return BitString a bit string with bits shifted by <tt>$shift</tt> to the right
	 */
	public function bitShiftRight($shift)
	{

	}

	/**
	 * Rotates the bits to the left.
	 *
	 * @param int $rot the length of the rotation
	 * @return BitString a bit string with <tt>$rot</tt> (mod length) leftmost bits moved to the back of the bit string
	 */
	public function bitRotateLeft($rot)
	{

	}

	/**
	 * Rotates the bits to the right.
	 *
	 * @param int $rot the length of the rotation
	 * @return BitString a bit string with <tt>$rot</tt> (mod length) rightmost bits moved to the front of the bit
	 *                     string
	 */
	public function bitRotateRight($rot)
	{

	}


	public function __toString()
	{
		return $this->toString();
	}


	/**
	 * @param int $offset the bit to know existence of; 0 for the leftmost bit, 1 for the next bit, etc.
	 * @return bool <tt>true</tt> if the bit string has <tt>$offset</tt>-th bit defined,
	 *              <tt>false</tt> if the bit string is too short (i.e., shorter than <tt>$offset+1</tt> bits)
	 */
	public function offsetExists($offset)
	{
		// TODO: Implement offsetExists() method.
	}

	/**
	 * @param int $offset the bit to get; 0 for the leftmost bit, 1 for the next bit, etc.
	 * @return int|null 0 or 1 depending on whether the <tt>$offset</tt>-th bit is set, or
	 *                  <tt>null</tt> if outside of the bit string
	 */
	public function offsetGet($offset)
	{
		// TODO: Implement offsetGet() method.
	}

	/**
	 * Setting a bit is not implemented - the bit string is immutable.
	 *
	 * @throws ImmutableException
	 */
	public function offsetSet($offset, $value)
	{
		throw new ImmutableException();
	}

	/**
	 * Unsetting a bit is not implemented - the bit string is immutable.
	 *
	 * @throws ImmutableException
	 */
	public function offsetUnset($offset)
	{
		throw new ImmutableException();
	}
}
