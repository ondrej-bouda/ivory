<?php
namespace Ivory\Value;

/**
 * Variable-length bit string - a string of 1's and 0's.
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
class VarBitString extends BitString
{
	/** @var int|null */
	private $maxLength;

	/**
	 * @param string $bits the bit string, i.e., a string of 1's and 0's
	 * @param int|null $maxLength maximal length of the bit string in bits;
	 *                            <tt>null</tt> for no limit;
	 *                            if less than <tt>strlen($bits)</tt>, the bits get truncated on the right and a warning
	 *                              is issued
	 * @return VarBitString
	 * @throws \InvalidArgumentException if <tt>$length</tt> is a non-positive number (PostgreSQL forbids it)
	 */
	public static function fromString($bits, $maxLength = null)
	{
		$bits = (string)$bits;

		if ($maxLength === null) {
			return new VarBitString($bits, null);
		}
		elseif ($maxLength <= 0) {
			throw new \InvalidArgumentException('maxLength is non-positive');
		}
		elseif ($maxLength < strlen($bits)) {
			trigger_error("Bit string truncated to the length of $maxLength", E_USER_WARNING);
			return new VarBitString(substr($bits, 0, $maxLength), $maxLength);
		}
		else {
			return new VarBitString($bits, $maxLength);
		}
	}

	/**
	 * @param string $bits
	 * @param int|null $maxLength
	 */
	protected function __construct($bits, $maxLength)
	{
		parent::__construct($bits);

		if ($maxLength !== null && $maxLength < 0) { // NOTE: zero max. length permitted for substr() to calculate correctly
			throw new \InvalidArgumentException('maxLength is negative');
		}
		$this->maxLength = $maxLength;
	}

	public function equals(BitString $other)
	{
		if (!parent::equals($other)) {
			return false;
		}
		/** @var VarBitString $other */
		return ($this->maxLength == $other->maxLength);
	}

	/**
	 * @return int|null maximal length this bit string might have carried, or <tt>null</tt> for unlimited length
	 */
	public function getMaxLength()
	{
		return $this->maxLength;
	}

	public function substr($offset, $length = null)
	{
		$newBits = substr($this->bits, $offset, $length);

		if ($this->maxLength === null) {
			$newMaxLen = null;
		}
		else {
			$newMaxLen = $this->maxLength - ($this->len - strlen($newBits));
		}

		return new VarBitString($newBits, $newMaxLen);
	}
}
