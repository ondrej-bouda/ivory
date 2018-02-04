<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Value\Alg\ComparableWithPhpOperators;
use Ivory\Value\Alg\IComparable;

/**
 * Representation of a time interval.
 *
 * The interval may be positive, negative ("ago"), or mixed (e.g., `'-1 year -2 mons +3 days'`).
 *
 * Internally, the interval is held as the number of months, days (both integers) and seconds (integer or float) - just
 * as in PostgreSQL. When creating an interval, the values are normalized - e.g., specifying an interval of 9 days is
 * effectively the same (and unrecognizable after creating the object) as specifying an interval of 1 week and 2 days.
 * Note that values are not carried over month-day or day-second borders - e.g., "36 hours" is different from
 * "1 day 12 hours".
 *
 * When specifying the interval, any quantity may be an arbitrary decimal number. Fractions are added to the lower-order
 * fields using the conversion factors 1 month = 30 days and 1 day = 24 hours.
 *
 * Besides being {@link IEqualable}, the {@link Timestamp} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * The objects are immutable, i.e., once constructed, their value cannot be changed.
 */
class TimeInterval implements IComparable
{
    use ComparableWithPhpOperators;

    const MILLENNIUM = 'millennium';
    const CENTURY = 'century';
    const DECADE = 'decade';
    const YEAR = 'year';
    const MONTH = 'month';
    const WEEK = 'week';
    const DAY = 'day';
    const HOUR = 'hour';
    const MINUTE = 'minute';
    const SECOND = 'second';
    const MILLISECOND = 'millisecond';
    const MICROSECOND = 'microsecond';

    private const PRECISION = 7;
    private const UNIT_ISO_ABBR = [
        self::YEAR => 'Y',
        self::MONTH => 'M',
        self::WEEK => 'W',
        self::DAY => 'D',
        self::HOUR => 'H',
        self::MINUTE => 'M',
        self::SECOND => 'S',
    ];
    private const TIME_UNIT_HASH = [
        self::HOUR => true,
        self::MINUTE => true,
        self::SECOND => true,
        self::MILLISECOND => true,
        self::MICROSECOND => true,
    ];

    // NOTE: the order of fields is important so that comparison with operators <, ==, and > works
    /** @var int */
    private $mon;
    /** @var int */
    private $day;
    /** @var int|float */
    private $sec;


    /**
     * @param number[] $parts map of units (any of {@link TimeInterval} constants) to the corresponding quantity
     * @return TimeInterval
     */
    public static function fromParts(array $parts): TimeInterval
    {
        $mon = 0;
        $day = 0;
        $sec = 0;

        // fractional quantities might lead to some units to be added repetitively
        $queue = [];
        foreach ($parts as $unit => $quantity) {
            $queue[] = [$unit, $quantity];
        }

        for (; ($pair = current($queue)) !== false; next($queue)) {
            list($unit, $quantity) = $pair;
            $intQuantity = (int)round($quantity, self::PRECISION);
            $fracQuantity = $quantity - $intQuantity;

            switch ($unit) {
                case self::MILLENNIUM:
                    $mon += $intQuantity * 12 * 1000;
                    if ($fracQuantity) {
                        $queue[] = [self::YEAR, $fracQuantity * 1000];
                    }
                    break;
                case self::CENTURY:
                    $mon += $intQuantity * 12 * 100;
                    if ($fracQuantity) {
                        $queue[] = [self::YEAR, $fracQuantity * 100];
                    }
                    break;
                case self::DECADE:
                    $mon += $intQuantity * 12 * 10;
                    if ($fracQuantity) {
                        $queue[] = [self::YEAR, $fracQuantity * 10];
                    }
                    break;
                case self::YEAR:
                    $mon += $intQuantity * 12;
                    if ($fracQuantity) {
                        $queue[] = [self::MONTH, $fracQuantity * 12];
                    }
                    break;
                case self::MONTH:
                    $mon += $intQuantity;
                    if ($fracQuantity) {
                        $queue[] = [self::DAY, $fracQuantity * 30];
                    }
                    break;
                case self::WEEK:
                    $day += $intQuantity * 7;
                    if ($fracQuantity) {
                        $queue[] = [self::DAY, $fracQuantity * 7];
                    }
                    break;
                case self::DAY:
                    $day += $intQuantity;
                    if ($fracQuantity) {
                        $queue[] = [self::SECOND, $fracQuantity * 24 * 60 * 60];
                    }
                    break;
                case self::HOUR:
                    $sec += $intQuantity * 60 * 60;
                    if ($fracQuantity) {
                        $queue[] = [self::SECOND, $fracQuantity * 60 * 60];
                    }
                    break;
                case self::MINUTE:
                    $sec += $intQuantity * 60;
                    if ($fracQuantity) {
                        $queue[] = [self::SECOND, $fracQuantity * 60];
                    }
                    break;
                case self::SECOND:
                    $sec += round($quantity, self::PRECISION);
                    break;
                case self::MILLISECOND:
                    $sec += round($quantity / 1000, self::PRECISION);
                    break;
                case self::MICROSECOND:
                    $sec += round($quantity / 100000, self::PRECISION);
                    break;
                default:
                    throw new \InvalidArgumentException("Undefined unit: '$unit'");
            }
        }
        return new TimeInterval($mon, $day, $sec);
    }

    /**
     * Creates a time interval from the PHP's standard {@link \DateInterval}.
     *
     * @param \DateInterval $dateInterval
     * @return TimeInterval
     */
    public static function fromDateInterval(\DateInterval $dateInterval): TimeInterval
    {
        $sgn = ($dateInterval->invert ? -1 : 1);
        return self::fromParts([
            self::YEAR => $sgn * $dateInterval->y,
            self::MONTH => $sgn * $dateInterval->m,
            self::DAY => $sgn * $dateInterval->d,
            self::HOUR => $sgn * $dateInterval->h,
            self::MINUTE => $sgn * $dateInterval->i,
            self::SECOND => $sgn * $dateInterval->s,
        ]);
    }

    /**
     * Creates a time interval from a string specification.
     *
     * Several formats are supported:
     * - ISO 8601 (e.g., `'P4DT5H'`, `'P1.5Y'`, `'P1,5Y'`, `'P0001-02-03'`, or `'P0001-02-03T04:05:06.7'`);
     * - SQL (e.g., `'200-10'` for 200 years and 10 months, or `'1 12:59:10'`);
     * - PostgreSQL (e.g., `'1 year 4.5 months 8 sec'`, `'@ 3 days 04:05:06'`, or just `'2'` for 2 seconds).
     *
     * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html#DATATYPE-INTERVAL-INPUT
     *
     * @param string $str interval specification in the ISO 8601, SQL, or PostgreSQL format
     * @return TimeInterval
     */
    public static function fromString(string $str): TimeInterval
    {
        if ($str[0] == 'P') {
            // ISO format
            $timeDelimPos = strpos($str, 'T');
            if (!$timeDelimPos) {
                $parts = self::parseIsoDateStr(substr($str, 1));
            } elseif ($timeDelimPos == 1) {
                $parts = self::parseTimeStr($str, 2);
            } else {
                $parts = self::parseIsoDateStr(substr($str, 1, $timeDelimPos - 1)) +
                    self::parseTimeStr($str, $timeDelimPos + 1);
            }
        } elseif ($str[0] == '@') {
            // verbose PostgreSQL format
            $parts = self::parsePostgresqlStr($str, 1);
        } elseif (preg_match('~^(?:(-)?(\d+)-(\d+))?\s*(-?\d+)??\s*(-?\d+(?::\d+(?::\d+(?:\.\d+)?)?)?)?$~', $str, $m)) {
            // sql format
            $parts = (isset($m[5]) ? self::parseTimeStr($m[5], 0, false) : []);
            if (!empty($m[2]) || !empty($m[3])) {
                $sgn = $m[1] . '1';
                $parts[self::YEAR] = $sgn * $m[2];
                $parts[self::MONTH] = $sgn * $m[3];
            }
            if (!empty($m[4])) {
                $parts[self::DAY] = (int)$m[4];
            }
        } else {
            // PostgreSQL format
            $parts = self::parsePostgresqlStr($str);
        }

        return self::fromParts($parts);
    }

    private static function parseIsoDateStr(string $str): array
    {
        if (preg_match('~^(-?\d+)-(-?\d+)-(-?\d+)$~', $str, $m)) {
            return [
                self::YEAR => (int)$m[1],
                self::MONTH => (int)$m[2],
                self::DAY => (int)$m[3],
            ];
        } else {
            $parts = [];
            preg_match_all('~(-?\d+(?:\.\d+)?)([YMDW])~', $str, $matches, PREG_SET_ORDER);
            static $units = ['Y' => self::YEAR, 'M' => self::MONTH, 'D' => self::DAY, 'W' => self::WEEK];
            foreach ($matches as $m) {
                $parts[$units[$m[2]]] = (float)$m[1];
            }
            return $parts;
        }
    }

    private static function parseTimeStr(string $str, int $offset = 0, bool $separateMinuteSigns = true): array
    {
        $timeRe = '~^
                     ( -? \d+ (?: \.\d+ )? )
                     (?: : ( -? \d+ (?:\.\d+)? ) )?
                     (?: : ( -? \d+ (?:\.\d+)? ) )?
                    $~x';
        if (preg_match($timeRe, substr($str, $offset), $m)) {
            if (isset($m[2])) {
                $sgn = ($separateMinuteSigns || $m[1] >= 0 ? 1 : -1);
                return [
                    self::HOUR => (float)$m[1],
                    self::MINUTE => $sgn * $m[2],
                    self::SECOND => $sgn * (isset($m[3]) ? (float)$m[3] : 0),
                ];
            } else {
                return [
                    self::SECOND => (float)$m[1],
                ];
            }
        } else {
            $parts = [];
            preg_match_all('~(-?\d+(?:\.\d+)?)([HMS])~', $str, $matches, PREG_SET_ORDER, $offset);
            static $units = ['H' => self::HOUR, 'M' => self::MINUTE, 'S' => self::SECOND];
            foreach ($matches as $m) {
                $parts[$units[$m[2]]] = (float)$m[1];
            }
            return $parts;
        }
    }

    private static function parsePostgresqlStr(string $str, int $offset = 0): array
    {
        static $pgUnits = [
            'millennium' => self::MILLENNIUM,
            'millenniums' => self::MILLENNIUM,
            'mil' => self::MILLENNIUM,
            'mils' => self::MILLENNIUM,
            'century' => self::CENTURY,
            'centuries' => self::CENTURY,
            'cent' => self::CENTURY,
            'decade' => self::DECADE,
            'decades' => self::DECADE,
            'dec' => self::DECADE,
            'decs' => self::DECADE,
            'year' => self::YEAR,
            'years' => self::YEAR,
            'y' => self::YEAR,
            'month' => self::MONTH,
            'months' => self::MONTH,
            'mon' => self::MONTH,
            'mons' => self::MONTH,
            'week' => self::WEEK,
            'weeks' => self::WEEK,
            'w' => self::WEEK,
            'day' => self::DAY,
            'days' => self::DAY,
            'd' => self::DAY,
            'hour' => self::HOUR,
            'hours' => self::HOUR,
            'h' => self::HOUR,
            'minute' => self::MINUTE,
            'minutes' => self::MINUTE,
            'min' => self::MINUTE,
            'mins' => self::MINUTE,
            'm' => self::MINUTE,
            'second' => self::SECOND,
            'seconds' => self::SECOND,
            'sec' => self::SECOND,
            'secs' => self::SECOND,
            's' => self::SECOND,
            'millisecond' => self::MILLISECOND,
            'milliseconds' => self::MILLISECOND,
            'microsecond' => self::MICROSECOND,
            'microseconds' => self::MICROSECOND,
        ];
        $parts = self::parseQuantityUnitPairs($str, $offset, $pgUnits);
        if (preg_match('~-?\d+:\d+:\d+(?:\.\d+)?~', $str, $m, 0, $offset)) {
            $parts += self::parseTimeStr($m[0]);
        }
        if (stripos($str, 'ago', $offset)) {
            foreach ($parts as &$quantity) {
                $quantity *= -1;
            } unset($quantity);
        }
        return $parts;
    }

    private static function parseQuantityUnitPairs(string $str, int $offset, array $units): array
    {
        $result = [];
        // OPT: the regular expression might be cached
        $re = '~(-?\d+(?:\.\d+)?)\s*(' . implode('|', array_map('preg_quote', array_keys($units))) . ')\b~i';
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, $offset);
        $unitsLower = array_change_key_case($units, CASE_LOWER); // OPT: keys $units might be required to be lower-case
        foreach ($matches as $m) {
            $unit = $unitsLower[strtolower($m[2])];
            $result[$unit] = (float)$m[1];
        }
        return $result;
    }

    private function __construct(int $mon, int $day, $sec)
    {
        $this->mon = $mon;
        $this->day = $day;
        $this->sec = $sec;
    }

    /**
     * @return number[] map: unit => quantity, the sum of which equals to the represented interval;
     *                  the output will consist of number of years, months, days, hours, minutes (all of which will
     *                    always be non-zero integers) and seconds (which may be fractional, and will be zero iff the
     *                    interval is zero);
     *                  the order of parts is guaranteed to be as mentioned in the previous sentence, i.e., from years
     *                    to seconds
     */
    public function toParts(): array
    {
        $result = [];
        $yr = (int)($this->mon / 12);
        $mon = $this->mon % 12;
        if ($yr != 0) {
            $result[self::YEAR] = $yr;
        }
        if ($mon != 0) {
            $result[self::MONTH] = $mon;
        }
        if ($this->day != 0) {
            $result[self::DAY] = $this->day;
        }
        $hr = (int)($this->sec / (60 * 60));
        $sec = $this->sec - $hr * 60 * 60;
        $min = (int)($sec / 60);
        $sec -= $min * 60;
        if ($hr != 0) {
            $result[self::HOUR] = $hr;
        }
        if ($min != 0) {
            $result[self::MINUTE] = $min;
        }
        if ($sec != 0 || !$result) {
            $result[self::SECOND] = $sec;
        }
        return $result;
    }

    public function toIsoString(): string
    {
        $str = '';
        $inDatePart = true;
        foreach ($this->toParts() as $unit => $quantity) {
            if ($inDatePart && isset(self::TIME_UNIT_HASH[$unit])) {
                $str .= 'T';
                $inDatePart = false;
            }
            $str .= $quantity . self::UNIT_ISO_ABBR[$unit];
        }
        return ($str ? "P$str" : 'PT0S');
    }

    /**
     * Adds a time interval to this interval and returns the result as a new time interval object.
     *
     * @param TimeInterval $addend
     * @return TimeInterval
     */
    public function add(TimeInterval $addend): TimeInterval
    {
        return new TimeInterval(
            $this->mon + $addend->mon,
            $this->day + $addend->day,
            $this->sec + $addend->sec
        );
    }

    /**
     * Subtracts a time interval from this interval and returns the result as a new time interval object.
     *
     * @param TimeInterval $subtrahend
     * @return TimeInterval
     */
    public function subtract(TimeInterval $subtrahend): TimeInterval
    {
        return new TimeInterval(
            $this->mon - $subtrahend->mon,
            $this->day - $subtrahend->day,
            $this->sec - $subtrahend->sec
        );
    }

    /**
     * Multiplies this time interval with a scalar and returns the result as a new time interval object.
     *
     * @param number $multiplier
     * @return TimeInterval
     */
    public function multiply($multiplier): TimeInterval
    {
        return new TimeInterval($multiplier * $this->mon, $multiplier * $this->day, $multiplier * $this->sec);
    }

    /**
     * Divides this time interval with a scalar and returns the result as a new time interval object.
     *
     * @param number $divisor
     * @return TimeInterval
     */
    public function divide($divisor): TimeInterval
    {
        return new TimeInterval($this->mon / $divisor, $this->day / $divisor, $this->sec / $divisor);
    }

    /**
     * @return TimeInterval time interval negative to this one
     */
    public function negate(): TimeInterval
    {
        return $this->multiply(-1);
    }
}
