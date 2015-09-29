<?php
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use Ivory\Utils\System;

/**
 * A decimal number with fixed precision.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 *
 * The representation and operations resemble the specification for numeric types in PostgreSQL, especially regarding
 * the precision of operation results. Generally, the scale of the result is computed such that the result is exactly
 * precise. However, operations which are inherently inexact have special treatment by PostgreSQL, which this class
 * tries to resemble: the result is of scale no less than any of the operands, and at least of a constant chosen such
 * that the result is at least as precise as the float8 operation would. This class uses similar result scale
 * computation and it also rounds the result at the computed scale, like PostgreSQL does (that is, the result is not
 * just truncated).
 *
 * The special static instance retrieved by {@link self::NaN()} represents the not-a-number value. In conformance with
 * PostgreSQL, a not-a-number only equals to a not-a-number, it is greater than any numeric value, and any operation
 * results in not-a-number if any of the operands is not-a-number.
 *
 * Regarding the implementation, using the GMP extension might be applicable for many operations, even for exact
 * operations on fractional numbers. The GMP extension is not built in PHP by default, however, so this is left for an
 * extension. Currently, only few integer operations use GMP if it is available. It would be better if an alternative
 * class was used if the presence of the GMP extension was discovered at runtime.
 *
 * @see http://www.postgresql.org/docs/current/static/datatype-numeric.html PostgreSQL Numeric Types
 * @see http://www.postgresql.org/docs/current/static/functions-math.html PostgreSQL Mathematical Functions and Operators
 */
class Decimal
{
	/** Maximal number of decimal digits considered by PostgreSQL. */
	const SCALE_LIMIT = 16383;
	/**
	 * Minimal number of significant digits for inexact calculations, like division or square root. It reflects the
	 * behaviour of PostgreSQL.
	 */
	const MIN_SIG_DIGITS = 16;

	/** @var string|null string of decimal digits, including the sign and decimal point; null for the not-a-number */
	private $val;
	/** @var int number of decimal digits in the fractional part, to the right of the decimal point (if any) */
	private $scale;

	/**
	 * @param string|int|float|Decimal|object $decimalNumber the value of the decimal number;
	 *                                                       leading zeros are ignored (even after the minus sign), as
	 *                                                         well as leading and trailing whitespace;
	 *                                                       if an object is passed, it gets cast to string (which works
	 *                                                         for {@link \GMP} objects, besides others)
	 * @param int|null $scale the requested scale (i.e., the number of decimal digits in the fractional part) of the
	 *                          number;
	 *                        must be non-negative, or null;
	 *                        if smaller than the scale of the given number, the number gets mathematically rounded to
	 *                          the requested scale;
	 *                        if greater than the scale of the given number, the number gets padded to have such many
	 *                          decimal places;
	 *                        if not given, it is computed from the given number automatically
	 * @return Decimal
	 */
	public static function fromNumber($decimalNumber, $scale = null)
	{
		if ($decimalNumber === null) {
			throw new \InvalidArgumentException('decimalNumber');
		}
		if ($scale < 0) {
			throw new \InvalidArgumentException('scale');
		}
		if ($decimalNumber instanceof Decimal) {
			if ($scale === null || $scale == $decimalNumber->scale) {
				return $decimalNumber;
			}
			else {
				return $decimalNumber->round($scale);
			}
		}

		if (is_int($decimalNumber)) {
			$val = (string)$decimalNumber;
		}
		elseif (is_float($decimalNumber)) {
			$val = (string)$decimalNumber;
			$ePos = stripos($val, 'e');
			if ($ePos > 0) {
				$exp = (int)substr($val, $ePos + 1);
				$decs = substr($val, 0, $ePos);
				list($wp, $dp) = explode('.', $decs, 2);

				if ($exp >= 0) {
					$dpLen = strlen($dp);
					if ($exp >= $dpLen) {
						$val = $wp . $dp . str_repeat('0', $exp - $dpLen);
					}
					else {
						$val = $wp . substr($dp, 0, $exp) . '.' . substr($dp, $exp);
					}
				}
				else {
					$mn = ($wp[0] == '-' ? 1 : 0);
					$ord = strlen($wp) - $mn;
					if (-$exp >= $ord) {
						$prefix = ($mn ? '-' : '') . '0.' . str_repeat('0', -$exp - $ord);
						if (($mn ? ($wp != '-0') : ($wp != '0'))) {
							$prefix .= substr($wp, $mn);
						}
						$val = $prefix . $dp;
					}
					else {
						$val = substr($wp, 0, $mn + $ord + $exp) . '.' . substr($wp, $mn + $ord + $exp) . $dp;
					}
				}
			}
		}
		else {
			if (!preg_match('~^\s*(-?)(?|(\.[0-9]+)|0*([0-9]+(?:\.[0-9]*)?))\s*$~', (string)$decimalNumber, $m)) {
				throw new \InvalidArgumentException('decimalNumber');
			}
			$val = $m[1] . $m[2]; // cuts off unnecessary leading zeros
		}

		return new Decimal($val, $scale);
	}

	/**
	 * Gets the not-a-number special value. Returns the same object every time.
	 *
	 * It only equals to the not-a-number, and is greater than any number value. The result of any operation is, again,
	 * the not-a-number.
	 *
	 * @return Decimal the not-a-number value
	 */
	public static function NaN()
	{
		static $dec = null;
		if ($dec === null) {
			$dec = new Decimal(null);
		}
		return $dec;
	}

	/**
	 * Gets the decimal object representing the number zero. Returns the same object every time.
	 *
	 * @return Decimal
	 */
	public static function zero()
	{
		static $dec = null;
		if ($dec === null) {
			$dec = new Decimal(0);
		}
		return $dec;
	}

	/**
	 * NOTE: The constructor is kept private so that extra sanity checks are performed on user input, while trusting the
	 *       results of the operations without these extra checks.
	 *
	 * @param string|null $val
	 * @param int|null $scale
	 */
	private function __construct($val, $scale = null)
	{
		if ($val === null) {
			$this->val = null;
			$this->scale = null;
			return;
		}

		$this->val = (string)$val;

		$decPoint = strpos($this->val, '.');
		if ($scale < 0) {
			$this->scale = 0;
			$minus = ($this->val[0] == '-' ? 1 : 0);
			if ($decPoint !== false) {
				$digits = substr($this->val, $minus, $decPoint - $minus);
			}
			else {
				$digits = ($minus ? substr($this->val, $minus) : $this->val);
			}
			$len = strlen($digits);
			$sigPos = $len + $scale;
			if ($sigPos < 0) {
				$this->val = '0';
				return;
			}
			$inc = ($digits[$sigPos] >= 5);
			if (!$inc && $sigPos == 0) {
				$this->val = '0';
				return;
			}
			for ($i = $sigPos; $i < $len; $i++) {
				$digits[$i] = '0';
			}
			if ($inc) {
				$augend = '1' . str_repeat('0', -$scale);
				$digits = bcadd($digits, $augend, 0);
			}
			$this->val = ($minus ? '-' : '') . $digits;
		}
		elseif ($decPoint === false) {
			if ($scale > 0) {
				$this->val .= '.' . str_repeat('0', $scale);
				$this->scale = $scale;
			}
			else {
				$this->scale = 0;
			}
		}
		else {
			$len = strlen($this->val);
			$this->scale = $len - $decPoint - 1;
			$scale = min(self::SCALE_LIMIT, ($scale === null ? $this->scale : max(0, $scale)));
			if ($this->scale < $scale) {
				$this->val .= str_repeat('0', $scale - $this->scale);
				$this->scale = $scale;
			}
			elseif ($this->scale > $scale) {
				$newLen = $len - ($this->scale - $scale);
				$inc = ($this->val[$newLen] >= 5);
				if ($scale == 0) {
					$newLen--;
				}
				$this->val = substr($this->val, 0, $newLen);
				if ($inc) {
					$mn = ($this->val[0] == '-' ? '-' : '');
					$augend = $mn . ($scale == 0 ? '1' : '.' . str_repeat('0', $scale - 1) . '1');
					$this->val = bcadd($this->val, $augend, $scale);
				}
				$this->scale = $scale;
			}

			if ($this->val[0] == '.') {
				$this->val = '0' . $this->val;
			}
			elseif ($this->val[0] == '-' && $this->val[1] == '.') {
				$this->val[0] = '0';
				$this->val = '-' . $this->val;
			}
		}

		// eliminate negative zero - PostgreSQL does not differentiate between zeros
		if ($this->val[0] == '-' && strspn($this->val, '-0.') == strlen($this->val)) {
			$this->val = substr($this->val, 1);
		}
	}


	/**
	 * @return int|null number of decimal digits in the fractional part, to the right of the decimal point (if any), or
	 *                  <tt>null</tt> for the not-a-number
	 */
	public function getScale()
	{
		return $this->scale;
	}

	/**
	 * Compare this number numerically with another number.
	 *
	 * Note that using the `==` operator checks that the two {@link Decimal} objects are of the same value and scale,
	 * which might or might not be desired. Such a difference only arises in the trailing fractional zero digits,
	 * though.
	 *
	 * @param string|int|float|Decimal|object $number number to compare this number with
	 * @return bool whether this number numerically equals to <tt>$number</tt>
	 */
	public function equals($number)
	{
		return ($this->compareTo($number) == 0);
	}

	/**
	 * @param string|int|float|Decimal|object $number
	 * @return bool <tt>true</tt> iff this number is numerically less than <tt>$number</tt>
	 */
	public function lessThan($number)
	{
		return ($this->compareTo($number) < 0);
	}

	/**
	 * @param string|int|float|Decimal|object $number
	 * @return bool <tt>true</tt> iff this number is numerically greater than <tt>$number</tt>
	 */
	public function greaterThan($number)
	{
		return ($this->compareTo($number) > 0);
	}

	/**
	 * @param string|int|float|Decimal|object $number number to compare this number with
	 * @return int -1, 0, or 1 if this number is numerically less than, equal to, or greater than <tt>$number</tt>
	 */
	public function compareTo($number)
	{
		$arg = self::fromNumber($number);
		if ($this->isNaN()) {
			if ($arg->isNaN()) {
				return 0;
			}
			else {
				return 1;
			}
		}
		elseif ($arg->isNaN()) {
			return -1;
		}
		else {
			return bccomp($this->val, $arg->val, max($this->scale, $arg->scale));
		}
	}

	/**
	 * @return bool whether this is a not-a-number
	 */
	public function isNaN()
	{
		return ($this->val === null);
	}

	/**
	 * @return bool whether this is zero
	 */
	public function isZero()
	{
		return ($this->equals(self::zero()));
	}

	/**
	 * @return bool whether this is a positive number
	 */
	public function isPositive()
	{
		return (!$this->isNaN() && $this->compareTo(self::zero()) > 0);
	}

	/**
	 * @return bool whether this is a negative number
	 */
	public function isNegative()
	{
		return (!$this->isNaN() && $this->val[0] == '-');
	}

	/**
	 * @return bool whether this is an integer, i.e., a number without decimal part (or the decimal part equal to zero)
	 */
	public function isInteger()
	{
		if ($this->isNaN()) {
			return false;
		}
		if (strpos($this->val, '.') === false) {
			return true;
		}
		for ($i = strlen($this->val) - 1; ; $i--) {
			switch ($this->val[$i]) {
				case '.':
					return true;
				case '0':
					break;
				default:
					return false;
			}
		}
		return true;
	}

	/**
	 * @param string|int|float|Decimal|object $augend
	 * @return Decimal a new decimal number, representing the result of sum of this and the given number
	 */
	public function add($augend)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($augend);
		if ($arg->isNaN()) {
			return $arg;
		}
		$scale = max($this->scale, $arg->scale);
		return new Decimal(bcadd($this->val, $arg->val, $scale + 1), $scale);
	}

	/**
	 * @param string|int|float|Decimal|object $subtrahend
	 * @return Decimal a new decimal number, representing the result of subtraction of this and the given number
	 */
	public function subtract($subtrahend)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($subtrahend);
		if ($arg->isNaN()) {
			return $arg;
		}
		$scale = max($this->scale, $arg->scale);
		return new Decimal(bcsub($this->val, $arg->val, $scale + 1), $scale);
	}

	/**
	 * @param string|int|float|Decimal|object $multiplicand
	 * @return Decimal a new decimal number, representing the result of multiplication of this and the given number
	 */
	public function multiply($multiplicand)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($multiplicand);
		if ($arg->isNaN()) {
			return $arg;
		}
		$scale = min($this->scale + $arg->scale, self::SCALE_LIMIT);
		return new Decimal(bcmul($this->val, $arg->val, $scale + 1), $scale);
	}

	/**
	 * @param string|int|float|Decimal|object $divisor
	 * @return Decimal a new decimal number, representing the result of division of this number with the given number
	 * @throws \RuntimeException if <tt>$divisor</tt> is zero
	 */
	public function divide($divisor)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($divisor);
		if ($arg->isNaN()) {
			return $arg;
		}
		$scale = max($this->scale, $arg->scale, self::MIN_SIG_DIGITS);
		return new Decimal(bcdiv($this->val, $arg->val, $scale + 1), $scale);
	}

	/**
	 * @param string|int|float|Decimal|object $modulus
	 * @return Decimal remainder of this number divided by <tt>$modulus</tt>
	 * @throws \RuntimeException if <tt>$modulus</tt> is zero
	 */
	public function mod($modulus)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($modulus);
		if ($arg->isNaN()) {
			return $arg;
		}
		if ($arg->isZero()) {
			throw new \RuntimeException('Division by zero');
		}

		// NOTE: bcmod() only calculates integer modulus
		$a = $this->abs();
		$b = $arg->abs();
		$m = $a->subtract($b->multiply($a->divide($b)->floor()));
		if ($this->isNegative()) {
			return $m->negate();
		}
		else {
			return $m;
		}
	}

	/**
	 * @todo more precise calculation of fractional powers - now only double precision is used
	 * @param string|int|float|Decimal|object $power
	 * @return Decimal this number powered to <tt>$number</tt>
	 * @throws \RuntimeException if this number is negative while <tt>$power</tt> is non-integer (that would lead to a
	 *                             complex result)
	 */
	public function pow($power)
	{
		if ($this->isNaN()) {
			return $this;
		}
		$arg = self::fromNumber($power);
		if ($arg->isNaN()) {
			return $arg;
		}

		$scale = max($this->scale, $arg->scale, self::MIN_SIG_DIGITS);

		// NOTE: bcpow() only takes integer powers
		if ($arg->isInteger()) {
			return new Decimal(bcpow($this->val, $arg->val, $scale + 1), $scale);
		}
		else {
			if ($this->isNegative()) {
				throw new \RuntimeException('Negative number raised to a non-integer power yields a complex result');
			}
			return self::fromNumber(pow($this->toFloat(), $arg->toFloat()));
		}
	}

	/**
	 * @return Decimal the absolute value of this number
	 */
	public function abs()
	{
		if ($this->isNegative()) {
			return $this->negate();
		}
		else {
			return $this;
		}
	}

	/**
	 * @return Decimal a new decimal number, representing the negative value of this number
	 */
	public function negate()
	{
		if ($this->isNaN() || $this->isZero()) {
			return $this;
		}
		elseif ($this->val[0] == '-') {
			return new Decimal(substr($this->val, 1));
		}
		else {
			return new Decimal('-' . $this->val);
		}
	}

	/**
	 * Computes the exact factorial of this number.
	 * Only defined on numbers of scale zero, i.e., those without decimal part, and on the not-a-number.
	 *
	 * In conformance with PostgreSQL, the factorial of non-positive numbers is defined to be 1.
	 *
	 * @return Decimal the factorial of this number
	 * @throws UndefinedOperationException if this number is neither a zero-scale number nor the not-a-number
	 */
	public function factorial()
	{
		if ($this->isNaN()) {
			return $this;
		}
		if ($this->scale > 0) {
			throw new UndefinedOperationException('Number with a decimal part');
		}
		if ($this->lessThan(2)) {
			return new Decimal(1);
		}

		if (System::hasGMP()) {
			return new Decimal(gmp_strval(gmp_fact($this->toGMP())));
		}

		// OPT: there are more efficient algorithms calculating the factorial; see, e.g., http://www.luschny.de/math/factorial/index.html
		$result = new Decimal(2);
		for ($i = new Decimal(3); $i->compareTo($this) <= 0; $i = $i->add(1)) {
			$result = $result->multiply($i);
		}
		return $result;
	}

	/**
	 * @param int $scale number of decimal places to round this number to; may be negative to round to higher orders
	 * @return Decimal a new decimal number, representing this number rounded to <tt>$scale</tt> decimal places
	 */
	public function round($scale = 0)
	{
		return new Decimal($this->val, $scale);
	}

	/**
	 * @return Decimal largest integer not greater than this number
	 */
	public function floor()
	{
		$decPoint = strpos($this->val, '.');
		if ($decPoint === false) {
			return $this;
		}
		if ($this->val[0] != '-') {
			return new Decimal(substr($this->val, 0, $decPoint));
		}
		elseif ($this->isInteger()) {
			return new Decimal($this->val, 0);
		}
		else {
			// negative number with non-zero fractional part
			return new Decimal(bcsub(substr($this->val, 0, $decPoint), 1, 0));
		}
	}

	/**
	 * @return Decimal smallest integer not less than this number
	 */
	public function ceil()
	{
		$decPoint = strpos($this->val, '.');
		if ($decPoint === false) {
			return $this;
		}
		if ($this->val[0] == '-') {
			return new Decimal(substr($this->val, 0, $decPoint));
		}
		elseif ($this->isInteger()) {
			return new Decimal($this->val, 0);
		}
		else {
			// non-negative number with non-zero fractional part
			return new Decimal(bcadd(substr($this->val, 0, $decPoint), 1, 0));
		}
	}

	/**
	 * @param string|int|float|Decimal|object $number
	 * @return Decimal the greater of this number and <tt>$number</tt>, preferably this number if numerically equal
	 */
	public function max($number)
	{
		$arg = self::fromNumber($number);
		if ($this->lessThan($arg)) {
			return $arg;
		}
		else {
			return $this;
		}
	}

	/**
	 * @param string|int|float|Decimal|object $number
	 * @return Decimal the smaller of this number and <tt>$number</tt>, preferably this number if numerically equal
	 */
	public function min($number)
	{
		$arg = self::fromNumber($number);
		if ($this->greaterThan($arg)) {
			return $arg;
		}
		else {
			return $this;
		}
	}

	/**
	 * @return Decimal a new decimal number, representing the square root of this number
	 * @throws UndefinedOperationException if this is a negative number
	 */
	public function sqrt()
	{
		if ($this->isNaN()) {
			return $this;
		}
		if ($this->isNegative()) {
			throw new UndefinedOperationException('square root of negative number');
		}
		$scale = max($this->scale, self::MIN_SIG_DIGITS);
		return new Decimal(bcsqrt($this->val, $scale + 1));
	}


	/**
	 * @return int|null the value of this number cast explicitly to <tt>int</tt>;
	 *                  for big numbers, this yields the maximal available integer value (i.e., <tt>PHP_INT_MAX</tt> or
	 *                    <tt>PHP_INT_MIN</tt>);
     *                  <tt>null</tt> is returned if this is the not-a-number
	 */
	public function toInt()
	{
		return ($this->val === null ? null : (int)$this->val);
	}

	/**
	 * @return float the value of this number cast explicitly to <tt>float</tt>, or the float <tt>NAN</tt> value if this
	 *                 is the not-a-number
	 */
	public function toFloat()
	{
		return ($this->val === null ? NAN : (float)$this->val);
	}

	/**
	 * @return string the value of this number, or the special string <tt>'NaN'</tt> for the not-a-number value
	 */
	public function toString()
	{
		return ($this->val === null ? 'NaN' : $this->val);
	}

	/**
	 * Converts the value to a {@link \GMP} object.
	 *
	 * Requires the `gmp` PHP extension.
	 *
	 * @return \GMP a {@link \GMP} object representing the same integer as this number
	 * @throws UndefinedOperationException if this number is not an integer, i.e., has some non-zero fractional digits
	 */
	public function toGMP()
	{
		if (!$this->isInteger()) {
			throw new UndefinedOperationException('toGMP() is only defined for integers');
		}
		$decPoint = strpos($this->val, '.');
		$str = ($decPoint === false ? $this->val : substr($this->val, 0, $decPoint - 1));
		return gmp_init($str);
	}

	public function __toString()
	{
		return $this->toString();
	}
}
