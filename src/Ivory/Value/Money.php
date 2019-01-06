<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\EqualableWithCompareTo;
use Ivory\Value\Alg\IComparable;

/**
 * A money amount.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 *
 * A money amount is merely a decimal number together with an optional money unit.
 *
 * Note that the special {@link Decimal::NaN()} values are not allowed for a money amount.
 *
 * @see https://www.postgresql.org/docs/11/datatype-money.html
 */
class Money implements IComparable
{
    use EqualableWithCompareTo;

    private $amount;
    private $unit;


    /**
     * @param string $moneyStr the money quantity as a string;
     *                         the value must use <tt>$decimalSeparator</tt> as the decimal separator, but may use any
     *                           thousands separators;
     *                         the unit may occur either before or after the actual value, or not at all;
     *                         whitespace is ignored altogether except for the unit
     * @param string $decimalSeparator
     * @return Money the representation of the given quantity
     * @throws \InvalidArgumentException if the string does not conform to the recognized syntax (which is iff it does
     *                                     not contain any decimal digit)
     */
    public static function fromString(string $moneyStr, string $decimalSeparator): Money
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

        if (!preg_match($re, $moneyStr, $m)) {
            throw new \InvalidArgumentException('$moneyStr');
        }

        $amount = preg_replace('~\D+~', '', $m[2]);
        if (isset($m[3]) && $m[3] !== '') {
            $amount .= '.' . preg_replace('~\D+~', '', $m[3]);
        }

        $decimal = Decimal::fromNumber($amount);
        $unit = ($m[1] !== '' ? $m[1] : (isset($m[4]) && $m[4] !== '' ? $m[4] : null));

        return new Money($decimal, $unit);
    }

    public static function fromNumber($amount, ?string $unit = null): Money
    {
        $decimal = Decimal::fromNumber($amount);
        if ($decimal->isNaN()) {
            throw new \InvalidArgumentException('$amount cannot be NaN');
        }

        return new Money($decimal, $unit);
    }


    private function __construct(Decimal $amount, ?string $unit)
    {
        $this->amount = $amount;
        $this->unit = $unit;
    }

    public function getAmount(): Decimal
    {
        return $this->amount;
    }

    /**
     * @return string|null the money unit (e.g., <tt>$</tt>), or <tt>null</tt> if no unit is specified for this amount
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function __toString()
    {
        return "{$this->amount} " . ($this->unit === null ? '(no unit)' : $this->unit);
    }

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException('comparing with null');
        }

        if (!$other instanceof Money) {
            throw new IncomparableException('$other is not of class ' . Money::class);
        }
        if ($this->getUnit() != $other->getUnit()) {
            throw new IncomparableException('Different monetary unit');
        }

        return $this->getAmount()->compareTo($other->getAmount());
    }
}
