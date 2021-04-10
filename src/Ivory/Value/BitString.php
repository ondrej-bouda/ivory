<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\IncomparableException;
use Ivory\Exception\UndefinedOperationException;
use Ivory\Value\Alg\IComparable;

/**
 * A common super type for bit string types - strings of 1's and 0's.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 * The representation and operations resemble the specification for bit string types in PostgreSQL.
 *
 * It is possible to access individual bits using the array indices (readonly). The leftmost bit is at offset 0. Testing
 * whether the bit string has a bit at a given offset may be performed using `isset($this[$offset])`. Note that, apart
 * from reading out of such a call, it does not test whether the given bit is *set* (i.e., whether it is 1) - it merely
 * tests whether it is legal to access it. Negative offsets may be used to get bits off the end of the string.
 *
 * @see https://www.postgresql.org/docs/11/datatype-bit.html PostgreSQL Bit String Types
 * @see https://www.postgresql.org/docs/11/functions-bitstring.html PostgreSQL Bit String Functions and Operators
 *
 * @todo optimize the internal representation (unpack('c*', $value) might be handy for constructing from string)
 */
abstract class BitString implements IComparable, \ArrayAccess
{
    protected $bits;
    protected $len;


    /**
     * @param string $bits
     */
    protected function __construct(string $bits)
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
    public function toString(): string
    {
        return $this->bits;
    }

    /**
     * @return int number of bits in the bit string
     */
    public function getLength(): int
    {
        return $this->len;
    }

    /**
     * @return bool whether all the bits are zero
     */
    public function isZero(): bool
    {
        return !$this->isNonZero();
    }

    /**
     * @return bool whether some bit is one
     */
    public function isNonZero(): bool
    {
        for ($i = 0; $i < $this->len; $i++) {
            if ($this->bits[$i] == '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param BitString|null $other
     * @return bool whether this and the other bit string are of the same type, have the same bits and same length
     */
    public function equals($other): bool
    {
        return (
            $other !== null &&
            get_class($this) == get_class($other) &&
            $this->bits == $other->bits
        );
    }

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException('comparing with null');
        }
        if (get_class($this) != get_class($other)) {
            throw new IncomparableException();
        }

        return strcmp($this->bits, $other->bits);
    }

    /**
     * @param BitString $other any bit string of any length
     * @return bool whether this and the other bit string have the same bits, i.e., are the same 1's and 0's strings
     */
    public function bitEquals(BitString $other): bool
    {
        return ($this->bits === $other->bits); // ===: PHP would otherwise convert both strings to integers
    }

    /**
     * Finds out whether the operands intersect at a 1 bit.
     *
     * Works for any lengths of the bit strings. The shorter bit string is right-padded with 0's.
     *
     * @param BitString $other any bit string of any length
     * @return bool whether this and the other bit string share at least one set bit at the same offset
     */
    public function intersects(BitString $other): bool
    {
        for ($i = min($this->len, $other->len) - 1; $i >= 0; $i--) {
            if ($this->bits[$i] == '1' && $other->bits[$i] == '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts a bit substring from this bit string.
     *
     * Note the semantics are according to the SQL standard, which PostgreSQL implements. That is, the arguments do NOT
     * work the same as for PHP {@link substr()} function.
     *
     * Examples:
     * - `substring(2)` on `'1101001'` yields `'101001'`
     * - `substring(2, 4)` on `'1101001'` yields `'1010'`
     * - `substring(2, 0)` on `'1101001'` yields `''`
     * - `substring(-2, 4)` on `'1101001'` yields `'1'`
     *
     * @param int $from position to start at;
     *                  one-based - e.g., <tt>$from = 2</tt> omits the first character;
     *                  if less than one, it yields the start of the string, but counting from the given (negative or
     *                    zero) position, effectively decreasing the <tt>$for</tt> argument, if given
     * @param int|null $for number of characters to take; must be non-negative;
     *                      <tt>null</tt> for the rest of the string
     * @return FixedBitString the substring of the bit string
     * @throws UndefinedOperationException when <tt>$for</tt> is negative
     */
    public function substring(int $from, ?int $for = null): FixedBitString
    {
        if ($for < 0) {
            throw new UndefinedOperationException('negative number of bits to take');
        }

        $offset = max(0, $from - 1);
        if ($offset >= $this->len) {
            return FixedBitString::fromString('');
        }

        if ($for === null) {
            $len = $this->len;
        } elseif ($from >= 0) {
            $len = $for;
        } else {
            $len = max(0, $for + $from - 1);
        }

        return FixedBitString::fromString(substr($this->bits, $offset, $len));
    }

    /**
     * Concatenates this bit string with another one.
     *
     * The result is a bit string with bits of this bit string followed by the bits from the `$concatenated` bit string.
     *
     * If both the operands are fixed-length of length `A` and `B`, the result is a fixed-length bit string of length
     * `A+B`. Otherwise, the result is a variable-length bit string.
     *
     * @param BitString $concatenated
     * @return VarBitString bit string made up by concatenating the <tt>$concatenated</tt> after this bit string
     */
    public function concat(BitString $concatenated): VarBitString
    {
        return VarBitString::fromString($this->bits . $concatenated->bits);
    }

    /**
     * Bitwise AND operation. Only legal for operands of equal bit lengths.
     *
     * @param BitString $other the other operand
     * @return FixedBitString new bit string: <tt>$this & $other</tt>
     * @throws UndefinedOperationException if the operands are of different bit lengths
     */
    public function bitAnd(BitString $other): FixedBitString
    {
        if ($this->len != $other->len) {
            throw new UndefinedOperationException('operands are of different bit lengths');
        }

        $res = str_repeat('0', $this->len);
        for ($i = 0; $i < $this->len; $i++) {
            if ($this->bits[$i] == '1' && $other->bits[$i] == '1') {
                $res[$i] = '1';
            }
        }

        return FixedBitString::fromString($res);
    }

    /**
     * Bitwise OR operation. Only legal for operands of equal bit lengths.
     *
     * @param BitString $other the other operand
     * @return FixedBitString new bit string: <tt>$this | $other</tt>
     * @throws UndefinedOperationException if the operands are of different bit lengths
     */
    public function bitOr(BitString $other): FixedBitString
    {
        if ($this->len != $other->len) {
            throw new UndefinedOperationException('operands are of different bit lengths');
        }

        $res = str_repeat('1', $this->len);
        for ($i = 0; $i < $this->len; $i++) {
            if ($this->bits[$i] == '0' && $other->bits[$i] == '0') {
                $res[$i] = '0';
            }
        }

        return FixedBitString::fromString($res);
    }

    /**
     * Bitwise exclusive OR operation. Only legal for operands of equal bit lengths.
     *
     * @param BitString $other the other operand
     * @return FixedBitString new bit string: <tt>$this ^ $other</tt>
     * @throws UndefinedOperationException if the operands are of different bit lengths
     */
    public function bitXor(BitString $other): FixedBitString
    {
        if ($this->len != $other->len) {
            throw new UndefinedOperationException('operands are of different bit lengths');
        }

        $res = str_repeat('0', $this->len);
        for ($i = 0; $i < $this->len; $i++) {
            if ($this->bits[$i] != $other->bits[$i]) {
                $res[$i] = '1';
            }
        }

        return FixedBitString::fromString($res);
    }

    /**
     * Bitwise negation, i.e., reverses all bits of the bit string.
     *
     * @return FixedBitString new bit string: <tt>~$this</tt>
     */
    public function bitNot(): FixedBitString
    {
        return FixedBitString::fromString(strtr($this->bits, '01', '10'));
    }

    /**
     * Shifts the bits to the left.
     *
     * The length of the bit string is preserved, thus, the `$shift` left trailing bits are discarded. The shifted bit
     * positions are filled with 0's.
     *
     * @param int $shift number of positions to shift the bits to the left;
     *                   might even be negative (results in shifting to the right by <tt>-$shift</tt>)
     * @return FixedBitString a bit string with bits shifted by <tt>$shift</tt> to the left
     */
    public function bitShiftLeft(int $shift): FixedBitString
    {
        if ($shift < 0) {
            return $this->bitShiftRight(-$shift);
        }

        $pad = min($this->len, $shift);
        $prefix = ($pad == $this->len ? '' : substr($this->bits, $pad));
        return FixedBitString::fromString($prefix . str_repeat('0', $pad));
    }

    /**
     * Shifts the bits to the right.
     *
     * The length of the bit string is preserved, thus, the `$shift` right trailing bits are discarded. The shifted bit
     * positions are filled with 0's.
     *
     * @param int $shift number of positions to shift the bits to the right;
     *                   might even be negative (results in shifting to the left by <tt>-$shift</tt>)
     * @return FixedBitString a bit string with bits shifted by <tt>$shift</tt> to the right
     */
    public function bitShiftRight(int $shift): FixedBitString
    {
        if ($shift < 0) {
            return $this->bitShiftLeft(-$shift);
        }

        $pad = min($this->len, $shift);
        $postfix = substr($this->bits, 0, $this->len - $pad);
        return FixedBitString::fromString(str_repeat('0', $pad) . $postfix);
    }

    /**
     * Rotates the bits to the left.
     *
     * @param int $rot the length of the rotation
     * @return FixedBitString a bit string with <tt>$rot</tt> (mod length) leftmost bits moved to the back of the bit
     *                          string
     */
    public function bitRotateLeft(int $rot): FixedBitString
    {
        if ($rot < 0) {
            return $this->bitRotateRight(-$rot);
        }
        if ($this->len == 0) {
            return FixedBitString::fromString('');
        }

        $pos = $rot % $this->len;
        return FixedBitString::fromString(substr($this->bits, $pos) . substr($this->bits, 0, $pos));
    }

    /**
     * Rotates the bits to the right.
     *
     * @param int $rot the length of the rotation
     * @return FixedBitString a bit string with <tt>$rot</tt> (mod length) rightmost bits moved to the front of the bit
     *                          string
     */
    public function bitRotateRight(int $rot): FixedBitString
    {
        if ($rot < 0) {
            return $this->bitRotateLeft(-$rot);
        }
        if ($this->len == 0) {
            return FixedBitString::fromString('');
        }

        $pos = $this->len - ($rot % $this->len);
        return FixedBitString::fromString(substr($this->bits, $pos) . substr($this->bits, 0, $pos));
    }


    public function __toString()
    {
        return $this->toString();
    }


    /**
     * @param int $offset the bit to know existence of; 0 for the leftmost bit, 1 for the next bit, -1 for the rightmost
     *                      bit, etc.
     * @return bool <tt>true</tt> if the bit string has <tt>$offset</tt>-th bit defined,
     *              <tt>false</tt> if the bit string is too short (i.e., shorter than <tt>$offset+1</tt> bits)
     */
    public function offsetExists($offset): bool
    {
        if ($offset < 0 && PHP_VERSION_ID < 70100) { // PHP < 7.1 did not support negative string offsets
            $offset = $this->len + $offset;
        }

        return isset($this->bits[$offset]);
    }

    /**
     * @param int $offset the bit to get; 0 for the leftmost bit, 1 for the next bit, -1 for the rightmost bit, etc.
     * @return int|null 0 or 1 depending on whether the <tt>$offset</tt>-th bit is set, or
     *                  <tt>null</tt> if outside of the bit string
     */
    public function offsetGet($offset): ?int
    {
        if ($offset < 0 && PHP_VERSION_ID < 70100) { // PHP < 7.1 did not support negative string offsets
            $offset = $this->len + $offset;
        }

        if (isset($this->bits[$offset])) {
            return (int)$this->bits[$offset];
        } else {
            return null;
        }
    }

    /**
     * Setting a bit is not implemented - the bit string is immutable.
     *
     * @param mixed $offset
     * @param mixed $value
     * @throws ImmutableException
     */
    public function offsetSet($offset, $value)
    {
        throw new ImmutableException();
    }

    /**
     * Unsetting a bit is not implemented - the bit string is immutable.
     *
     * @param mixed $offset
     * @throws ImmutableException
     */
    public function offsetUnset($offset)
    {
        throw new ImmutableException();
    }
}
