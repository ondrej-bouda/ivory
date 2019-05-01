<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Timezone-aware representation of time of day (no date, just time).
 *
 * For a timezone-unaware time, see {@link Time}.
 *
 * The supported range for the time part is from `00:00:00` to `24:00:00`. Fractional seconds may be used.
 *
 * Note that just the offset from GMT is recorded with the time, not the timezone identifier. E.g., if the connection
 * is configured for the `Europe/Prague` timezone, PostgreSQL puts `+0100` or `+0200` on output (depending on whether
 * the daylight "savings" time is in effect). That differs from the {@link TimestampTz} type, which records the
 * timezone, not just its offset.
 *
 * The objects are {@link IEqualable}, which considers two time representations equal only if they have both the time
 * part and the timezone offset equal. The same logic is used for the PHP `==` operator. Behaviour of the PHP `<` and
 * `>` operators is undefined. To compare the physical time of two {@link TimeTz} objects, use the
 * {@link TimeTz::occursBefore()}, {@link TimeTz::occursAt()} and {@link TimeTz::occursAfter()} methods.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-datetime.html
 */
class TimeTz extends TimeBase
{
    /** @var int difference to UTC in seconds; positive to the east of GMT, negative to the west of GMT */
    private $offset;

    /**
     * Creates a timezone-aware time object from a string containing the time and the timezone offset.
     *
     * The accepted format is `H:M[:S[.p]][offset]`, where `H` holds hours (0-24), `M` minutes (0-59), `S` seconds
     * (0-60), `p` fractional seconds, `offset` is the timezone offset written in the ISO 8601 format (e.g., `+2:00` or
     * `-800`). If the timezone offset is not given, the current offset is used.
     *
     * Note that, although 60 is accepted in the seconds part, it gets automatically converted to 0 of the next minute,
     * as neither PostgreSQL supports leap seconds.
     *
     * @param string $timeString
     * @return TimeTz
     * @throws \InvalidArgumentException on invalid input
     * @throws \OutOfRangeException when some of the parts is outside its range
     */
    public static function fromString(string $timeString): TimeTz
    {
        $re = '~^
                (\d+):                  # hours
                (\d+)                   # minutes
                (?::(\d+(?:\.\d*)?))?   # optional seconds, possibly with fractional part
                (?P<zone>               # optional timezone specification
                 Z|                     # ...either the "Z" letter or offset
                 (?P<offh>[-+]\d{1,2})  # ...or two digits for hours offset
                 (?::?(?P<offm>\d{2}))? # ...and possibly two digits for minutes offset, optionally separated by a colon
                )?
                $~x';
        if (!preg_match($re, $timeString, $m)) {
            throw new \InvalidArgumentException('$timeString');
        }

        $hour = $m[1];
        $min = $m[2];
        $sec = (!empty($m[3]) ? $m[3] : 0);

        if ($hour == 24) {
            if ($min > 0 || $sec > 0) {
                throw new \OutOfRangeException('with hour 24, the minutes and seconds must be zero');
            }
        } elseif ($hour < 0 || $hour > 24) {
            throw new \OutOfRangeException('hours');
        }

        if ($min < 0 || $min > 59) {
            throw new \OutOfRangeException('minutes');
        }

        if ($sec < 0 || $sec >= 61) {
            throw new \OutOfRangeException('seconds');
        }

        if (!isset($m['zone'])) {
            try {
                $dateTime = new \DateTime();
            } catch (\Exception $e) {
                throw new \LogicException('Date/time error', 0, $e);
            }
            $offset = ($dateTime)->getOffset();
        } elseif ($m['zone'] == 'Z') {
            $offset = 0;
        } else {
            $offset = $m['offh'] * 60 * 60;
            if (isset($m['offm'])) {
                $offset += ($m['offh'] >= 0 ? 1 : -1) * $m['offm'] * 60;
            }
        }

        return new TimeTz($hour * 60 * 60 + $min * 60 + $sec, $offset);
    }

    /**
     * Creates a timezone-aware time object from the timezone offset and specified hours, minutes, and seconds.
     *
     * Any part exceeding its standard range overflows in the expected way to the higher part. E.g., it is possible to
     * pass 70 seconds, which results, in 1 minute 10 seconds. Moreover, the arithmetic also works for subtracting
     * negative minutes or seconds. Still, the overall time must fit between 00:00:00 and 24:00:00.
     *
     * The overflow rule applies to leap seconds as well as to any other value, i.e., 60 seconds get converted to 0 of
     * the next minute, as neither PostgreSQL supports leap seconds.
     *
     * @param int $hour
     * @param int $minute
     * @param int|float $second
     * @param int $offset the timezone offset of this time from the Greenwich Mean Time, in seconds;
     *                    positive for east of Greenwich, negative for west of Greenwich
     * @return TimeTz
     * @throws \OutOfRangeException when the resulting time underruns 00:00:00 or exceeds 24:00:00
     */
    public static function fromParts(int $hour, int $minute, $second, int $offset): TimeTz
    {
        return new TimeTz(self::partsToSec($hour, $minute, $second), $offset);
    }

    /**
     * Creates a timezone-aware time object from the timezone offset and specified hours, minutes, and seconds with
     * range checks for each of them.
     *
     * Note that, although 60 is accepted in the seconds part, it gets automatically converted to 0 of the next minute,
     * as neither PostgreSQL supports leap seconds. In this case it is possible to get time even greater than 24:00:00
     * (but still less than 24:00:01).
     *
     * @param int $hour 0-24, but when 24, the others must be zero
     * @param int $minute 0-59
     * @param int|float $second greater than or equal to 0, less than 61
     * @param int $offset the timezone offset of this time from the Greenwich Mean Time, in seconds;
     *                    positive for east of Greenwich, negative for west of Greenwich
     * @return TimeTz
     * @throws \OutOfRangeException when some of the parts is outside its range
     */
    public static function fromPartsStrict(int $hour, int $minute, $second, int $offset): TimeTz
    {
        $sec = self::partsToSecStrict($hour, $minute, $second);
        return new TimeTz($sec, $offset);
    }

    /**
     * Extracts the time part and timezone offset from a {@link \DateTime} or {@link \DateTimeImmutable} object.
     *
     * @param \DateTimeInterface $dateTime
     * @return TimeTz
     */
    public static function fromDateTime(\DateTimeInterface $dateTime): TimeTz
    {
        return self::fromString($dateTime->format('H:i:s.uP'));
    }

    /**
     * Extracts the time part from a UNIX timestamp as the time in the UTC timezone.
     *
     * Negative timestamps are supported. E.g., timestamp `-30.1` results in time `23:59:29.9`.
     *
     * Note there is one exception: the timestamp `1970-01-02 00:00:00 UTC` gets extracted as time `24:00:00` so that
     * there is symmetry with {@link TimeTz::toUnixTimestamp()}. Other timestamps are processed as expected, i.e., the
     * day part gets truncated and the result being less than `24:00:00`.
     *
     * @param int|float $timestamp
     * @return TimeTz
     */
    public static function fromUnixTimestamp($timestamp): TimeTz
    {
        $sec = self::cutUnixTimestampToSec($timestamp);
        return new TimeTz($sec, 0);
    }

    /**
     * @internal Only for the purpose of Ivory itself.
     * @param int|float $sec
     * @param int $offset
     */
    protected function __construct($sec, int $offset)
    {
        parent::__construct($sec);
        $this->offset = $offset;
    }

    /**
     * @return int the timezone offset of this time from the Greenwich Mean Time, in seconds;
     *             positive for east of Greenwich, negative for west of Greenwich
     */
    final public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return string the timezone offset of this time from the Greenwich Mean Time formatted according to ISO 8601
     *                  using no delimiter, e.g., <tt>+0200</tt> or <tt>-0830</tt>
     */
    final public function getOffsetISOString(): string
    {
        return sprintf('%s%02d%02d',
            ($this->offset >= 0 ? '+' : '-'),
            abs($this->offset) / (60 * 60),
            (abs($this->offset) / 60) % 60
        );
    }

    public function toUnixTimestamp($date = null)
    {
        return parent::toUnixTimestamp($date) - $this->offset;
    }

    /**
     * @param TimeTz $other
     * @return bool whether this and the other time happen in the exact same moment
     */
    public function occursAt(TimeTz $other): bool
    {
        return ($this->toUnixTimestamp() == $other->toUnixTimestamp());
    }

    /**
     * @param TimeTz $other
     * @return bool whether this time happens before the given other time
     */
    public function occursBefore(TimeTz $other): bool
    {
        return ($this->toUnixTimestamp() < $other->toUnixTimestamp());
    }

    /**
     * @param TimeTz $other
     * @return bool whether this time happens after the given other time
     */
    public function occursAfter(TimeTz $other): bool
    {
        return ($this->toUnixTimestamp() > $other->toUnixTimestamp());
    }

    /**
     * @param string $timeFmt the format string as accepted by {@link date()}
     * @return string the time formatted according to <tt>$timeFmt</tt>
     */
    public function format(string $timeFmt): string
    {
        $str = $this->toString();
        try {
            $ts = new \DateTime($str);
            // OPT: \DateTime::createFromFormat() is supposed to be twice as fast as new \DateTime()
        } catch (\Exception $e) {
            throw new \LogicException('Date/time error', 0, $e);
        }
        $ts->setDate(1970, 1, 1);
        return $ts->format($timeFmt);
    }

    /**
     * @return string the ISO representation of this time, in format <tt>HH:MM:SS[.p](+|-)hhmm</tt>, where <tt>hh</tt>
     *                  and <tt>mm</tt> represent the timezone offset in hours and minutes, respectively;
     *                the fractional seconds part is only used if non-zero
     */
    public function toString(): string
    {
        return parent::toString() . $this->getOffsetISOString();
    }
}
