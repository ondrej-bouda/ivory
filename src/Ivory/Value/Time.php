<?php
namespace Ivory\Value;

use Ivory\Utils\IComparable;

/**
 * Representation of time of day (no date, just time).
 *
 * The supported range is from `00:00:00` to `24:00:00`. Fractional seconds may be used.
 *
 * Besides being {@link IComparable}, the {@link Date} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 */
class Time implements IComparable
{
    /** Number of decimal digits of precision in the fractional seconds part. */
    const PRECISION = 6;

    /** @var int|float */
    private $sec;

    /**
     * Creates a time object from a string containing the time.
     *
     * The accepted format is `H:M[:S[.p]]`, where `H` holds hours (0-24), `M` minutes (0-59), `S` seconds (0-60), `p`
     * fractional seconds. Note that, although 60 is accepted in the seconds part, it gets automatically converted to
     * 0 of the next minute, as neither PostgreSQL supports leap seconds.
     *
     * @param string $timeString
     * @return Time
     * @throws \InvalidArgumentException on invalid input
     * @throws \OutOfRangeException when some of the parts is outside its range
     */
    public static function fromString($timeString)
    {
        if (!preg_match('~^(\d+):(\d+)(?::(\d+(?:\.\d*)?))?$~', $timeString, $m)) {
            throw new \InvalidArgumentException('$timeString');
        }

        $hour = $m[1];
        $min = $m[2];
        $sec = (isset($m[3]) ? $m[3] : 0); // PHP 7: abbreviate using ??

        if ($hour == 24) {
            if ($min > 0 || $sec > 0) {
                throw new \OutOfRangeException('with hour 24, the minutes and seconds must be zero');
            }
        }
        elseif ($hour < 0 || $hour > 24) {
            throw new \OutOfRangeException('hours');
        }

        if ($min < 0 || $min > 59) {
            throw new \OutOfRangeException('minutes');
        }

        if ($sec < 0 || $sec >= 61) {
            throw new \OutOfRangeException('seconds');
        }

        return new Time($hour * 60 * 60 + $min * 60 + $sec);
    }

    /**
     * Extracts the time part from a UNIX timestamp.
     *
     * Negative timestamps are supported. E.g., timestamp `-30.1` results in time `23:59:29.9`.
     *
     * Note there is one exception: the timestamp `1970-01-02 00:00:00 UTC` gets extracted as time `24:00:00` so that
     * there is symmetry with {@link Time::toTimestamp()}. Other timestamps are processed as expected, i.e., the day
     * part gets truncated and the result being less then `24:00:00`.
     *
     * @param int|float $timestamp
     * @return Time
     */
    public static function fromTimestamp($timestamp)
    {
        if ($timestamp == 24 * 60 * 60) {
            return new Time($timestamp);
        }

        $dayRes = (int)($timestamp - ($timestamp % (24 * 60 * 60)));
        $sec = $timestamp - $dayRes;
        if ($sec < 0) {
            $sec += 24 * 60 * 60;
        }
        return new Time($sec);
    }

    /**
     * Extracts the time part from a {@link \DateTime} or {@link DateTimeImmutable} object.
     *
     * @param \DateTimeInterface $dateTime
     * @return Time
     */
    public static function fromDateTime(\DateTimeInterface $dateTime)
    {
        return self::fromString($dateTime->format('H:i:s.u'));
    }

    private function __construct($sec)
    {
        $this->sec = $sec;
    }

    /**
     * @return int the hours part of the time (0-24)
     */
    public function getHours()
    {
        return (int)($this->sec / (60 * 60));
    }

    /**
     * @return int the minutes part of the time (0-59)
     */
    public function getMinutes()
    {
        return ($this->sec / 60) % 60;
    }

    /**
     * @return int|float the seconds part of the time (0-59), potentially with the fractional part, if any
     */
    public function getSeconds()
    {
        return $this->sec - $this->getMinutes() * 60 - $this->getHours() * 60 * 60;
    }

    /**
     * @return string the ISO representation of this time, in format <tt>HH:MM:SS[.p]</tt>;
     *                the fractional seconds part is only used if non-zero
     */
    public function toString()
    {
        $frac = round($this->sec - (int)$this->sec, self::PRECISION);
        return sprintf(
            '%02d:%02d:%02d%s',
            $this->getHours(), $this->getMinutes(), $this->getSeconds(),
            ($frac ? substr($frac, 1) : '') // cut off the leading "0" for non-zero fractional seconds
        );
    }

    /**
     * @param string $timeFmt the format string as accepted by {@link date()}
     * @return string the time formatted according to <tt>$timeFmt</tt>
     */
    public function format($timeFmt)
    {
        if (strpos($timeFmt, 'u') !== false) {
            $frac = round($this->sec - (int)$this->sec, self::PRECISION);
            $fracStr = ($frac ? substr($frac, 2) : '0'); // cut off the leading "0." for non-zero fractional seconds

            $re = '~
                   (?<!\\\\)            # not prefixed with a backslash
                   ((?:\\\\\\\\)*)      # any number of pairs of backslashes, each meaning a single literal backslash
                   u                    # the microseconds format character to be replaced
                   ~x';
            $timeFmt = preg_replace($re, '${1}' . $fracStr, $timeFmt);
        }
        return gmdate($timeFmt, $this->sec);
    }

    /**
     * @param Date|string|null $date the date for the resulting timestamp;
     *                               besides a {@link Date} object, an ISO date string is accepted - see
     *                                 {@link Date::fromISOString()};
     *                               the given date (if any) must be finite;
     *                               if not given the time on 1970-01-01 is returned, which is effectively the amount of
     *                                 time, in seconds, between the time this object represents and <tt>00:00:00</tt>
     * @return float|int the UNIX timestamp of this time on the given day
     * @throws \InvalidArgumentException if the date is finite or if the <tt>$date</tt> string is not a valid ISO date
     *                                     string
     */
    public function toTimestamp($date = null)
    {
        if ($date === null) {
            return $this->sec;
        }
        else {
            if (!$date instanceof Date) {
                $date = Date::fromISOString($date);
            }
            $dayTs = $date->toTimestamp();
            if ($dayTs !== null) {
                return $dayTs + $this->sec;
            }
            else {
                throw new \InvalidArgumentException('infinite date');
            }
        }
    }

    //region IComparable

    /**
     * @param object $object
     * @return bool|null <tt>true</tt> if <tt>$this</tt> and the other <tt>$object</tt> are equal to each other,
     *                   <tt>false</tt> if they are not equal,
     *                   <tt>null</tt> iff <tt>$object</tt> is <tt>null</tt>
     */
    public function equals($object)
    {
        if ($object === null) {
            return null;
        }
        return ($this == $object);
    }

    //endregion
}
