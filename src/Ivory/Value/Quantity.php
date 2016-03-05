<?php
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use Ivory\Exception\UnsupportedException;
use Ivory\Utils\IComparable;

/**
 * A quantity with a unit.
 *
 * Stores the quantity as an `int` or `float` and the unit as a mere string.
 *
 * Several units are recognized and conversion to some of its related units is offered. See the class constants.
 * Note that the memory and time units are compatible with those used by PostgreSQL for configuration settings.
 *
 * The PHP operator `==` may be used to compare two `Quantity` objects whether they are of the same value and unit.
 * For comparison of effective values (e.g., `15s == 15000ms`), taking into account units convertibility, use the
 * {@link Quantity::equals()} method.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 */
class Quantity implements IComparable
{
    const BYTE = 'B';
    /** Memory unit of 1024 bytes. (For accordance with PostgreSQL, 1024 is used as the multiplier, not 1000.) */
    const KILOBYTE = 'kB';
    /** Memory unit of 1024^2 bytes. (For accordance with PostgreSQL, 1024 is used as the multiplier, not 1000.) */
    const MEGABYTE = 'MB';
    /** Memory unit of 1024^3 bytes. (For accordance with PostgreSQL, 1024 is used as the multiplier, not 1000.) */
    const GIGABYTE = 'GB';
    /** Memory unit of 1024^4 bytes. (For accordance with PostgreSQL, 1024 is used as the multiplier, not 1000.) */
    const TERABYTE = 'TB';

    const MILLISECOND = 'ms';
    const SECOND = 's';
    const MINUTE = 'min';
    const HOUR = 'h';
    const DAY = 'd';

    private static $conversion = [
        self::BYTE => [self::BYTE, 1],
        self::KILOBYTE => [self::BYTE, 1024],
        self::MEGABYTE => [self::BYTE, 1024 * 1024],
        self::GIGABYTE => [self::BYTE, 1024 * 1024 * 1024],
        self::TERABYTE => [self::BYTE, 1024 * 1024 * 1024 * 1024],

        self::MILLISECOND => [self::SECOND, 1/1000],
        self::SECOND => [self::SECOND, 1],
        self::MINUTE => [self::SECOND, 60],
        self::HOUR => [self::SECOND, 60 * 60],
        self::DAY => [self::SECOND, 24 * 60 * 60],
    ];
    private static $epsilon = 1e-9;


    private $value;
    private $unit;

    /**
     * @param string $quantityStr the quantity as a string;
     *                            the value must use <tt>$decimalSeparator</tt> as the decimal separator, but may use
     *                              any thousands separators;
     *                            the unit may occur either before or after the actual value, or not at all;
     *                            whitespace is ignored altogether except for the unit
     * @param string $decimalSeparator
     * @return Quantity the representation of the given quantity
     * @throws \InvalidArgumentException if the string does not conform to the recognized syntax (which is iff it does
     *                                     not contain any decimal digit)
     */
    public static function fromString($quantityStr, $decimalSeparator = '.')
    {
        $ds = preg_quote($decimalSeparator, '~');
        $re = '~^ \s*
                (\D+?)?                             # optional unit before the amount
                \s*
                (\d(?:[^' . $ds . '\d]*\d+)*)       # the integer part of the amount
                (?:' . $ds . '((?2)))?              # optional decimal part of the amount
                \s*
                (?(1)|(\D*?))                       # optional unit after the amount, if there was no unit before
                \s*
                $~x';

        if (!preg_match($re, $quantityStr, $m)) {
            throw new \InvalidArgumentException('$quantityStr');
        }

        $val = preg_replace('~\D+~', '', $m[2]);
        if (isset($m[3]) && $m[3] !== '') {
            $val .= '.' . preg_replace('~\D+~', '', $m[3]);
            $val = (float)$val;
        }
        else {
            $val = (int)$val;
        }

        $unit = ($m[1] !== '' ? $m[1] : (isset($m[4]) && $m[4] !== '' ? $m[4] : null));

        return new Quantity($val, $unit);
    }

    public static function fromValue($value, $unit = null)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('$value');
        }

        if ($unit === '') {
            $unit = null;
        }

        return new Quantity($value, $unit);
    }

    private function __construct($value, $unit)
    {
        $this->value = $value;
        $this->unit = $unit;
    }

    /**
     * @return int|float
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * @param Quantity|string $quantity quantity to compare this quantity with;
     *                                  if Quantity is not given, {@link Quantity::fromString()} is used to parse it
     * @return bool whether the two quantities are comparable and of equal value (after converting to the same unit if
     *                convertible)
     */
    public function equals($quantity)
    {
        if ($quantity === null) {
            return null;
        }
        if (!$quantity instanceof Quantity) {
            $quantity = self::fromString($quantity);
        }

        if ($this->unit == $quantity->unit) {
            $thisNormValue = $this->value;
            $quanNormValue = $quantity->value;
        }
        else {
            if (!isset(self::$conversion[$this->unit], self::$conversion[$quantity->unit])) {
                return false;
            }

            list($thisBase, $thisMult) = self::$conversion[$this->unit];
            list($quanBase, $quanMult) = self::$conversion[$quantity->unit];
            if ($thisBase != $quanBase) {
                return false;
            }

            $thisNormValue = $this->value * $thisMult;
            $quanNormValue = $quantity->value * $quanMult;
        }

        return (abs($thisNormValue - $quanNormValue) < self::$epsilon);
    }

    /**
     * Converts the quantity to a given unit.
     *
     * @param string $destUnit one of the recognized units
     * @return Quantity
     * @throws UnsupportedException if conversion between the current and destination units is not supported
     * @throws UndefinedOperationException if the current and destination units are not convertible from one to another
     */
    public function convert($destUnit)
    {
        if (!$this->unit || !$destUnit) {
            if ($this->value == 0) {
                return new Quantity($this->value, $destUnit);
            }
            else {
                throw new UndefinedOperationException('Conversion from/to dimensionless quantity is undefined.');
            }
        }
        if (!isset(self::$conversion[$this->unit])) {
            throw new UnsupportedException("Conversion from an unsupported unit '$this->unit'");
        }
        if (!isset(self::$conversion[$destUnit])) {
            throw new UnsupportedException("Conversion to an unsupported unit '$destUnit'");
        }

        list($thisBase, $thisMult) = self::$conversion[$this->unit];
        list($destBase, $destMult) = self::$conversion[$destUnit];
        if ($thisBase != $destBase) {
            throw new UndefinedOperationException("Conversion from '$this->unit' to '$destUnit' is not defined.");
        }

        $destValue = $this->value * $thisMult / $destMult;
        if (abs($destValue - (int)$destValue) < self::$epsilon) {
            $destValue = (int)$destValue;
        }

        return new Quantity($destValue, $destUnit);
    }

    /**
     * @return string the value followed by the unit (if any), separated by a single space
     */
    public function toString()
    {
        $str = (string)$this->value;
        if (strlen($this->unit) > 0) {
            $str .= ' ' . $this->unit;
        }
        return $str;
    }

    public function __toString()
    {
        return $this->toString();
    }
}
