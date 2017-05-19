<?php
namespace Ivory\Value;

/**
 * Representation of time of day (no date, just time).
 *
 * No timezone information is handled by this class - see {@link TimeTz} instead.
 *
 * The supported range is from `00:00:00` to `24:00:00`. Fractional seconds may be used.
 *
 * Besides being {@link IEqualable}, the {@link Time} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 */
class Time extends TimeBase
{
    /**
     * Creates a time object from a string containing the time.
     *
     * The accepted format is `H:M[:S[.p]]`, where `H` holds hours (0-24), `M` minutes (0-59), `S` seconds (0-60), `p`
     * fractional seconds.
     *
     * Note that, although 60 is accepted in the seconds part, it gets automatically converted to 0 of the next minute,
     * as neither PostgreSQL supports leap seconds.
     *
     * @param string $timeString
     * @return Time
     * @throws \InvalidArgumentException on invalid input
     * @throws \OutOfRangeException when some of the parts is outside its range
     */
    public static function fromString(string $timeString): Time
    {
        if (!preg_match('~^(\d+):(\d+)(?::(\d+(?:\.\d*)?))?$~', $timeString, $m)) {
            throw new \InvalidArgumentException('$timeString');
        }

        $hour = $m[1];
        $min = $m[2];
        $sec = ($m[3] ?? 0);

        return self::fromPartsStrict($hour, $min, $sec);
    }

    /**
     * Creates a time object from the specified hours, minutes, and seconds.
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
     * @return Time
     * @throws \OutOfRangeException when the resulting time underruns 00:00:00 or exceeds 24:00:00
     */
    public static function fromParts(int $hour, int $minute, $second): Time
    {
        return new Time(self::partsToSec($hour, $minute, $second));
    }

    /**
     * Creates a time object from the specified hours, minutes, and seconds with range checks for each of them.
     *
     * Note that, although 60 is accepted in the seconds part, it gets automatically converted to 0 of the next minute,
     * as neither PostgreSQL supports leap seconds. In this case it is possible to get time even greater than 24:00:00
     * (but still less than 24:00:01).
     *
     * @param int $hour 0-24, but when 24, the others must be zero
     * @param int $minute 0-59
     * @param int|float $second greater than or equal to 0, less than 61
     * @return Time
     * @throws \OutOfRangeException when some of the parts is outside its range
     */
    public static function fromPartsStrict(int $hour, int $minute, $second): Time
    {
        return new Time(self::partsToSecStrict($hour, $minute, $second));
    }

    /**
     * Extracts the time part from a UNIX timestamp.
     *
     * Negative timestamps are supported. E.g., timestamp `-30.1` results in time `23:59:29.9`.
     *
     * Note there is one exception: the timestamp `1970-01-02 00:00:00 UTC` gets extracted as time `24:00:00` so that
     * there is symmetry with {@link Time::toUnixTimestamp()}. Other timestamps are processed as expected, i.e., the day
     * part gets truncated and the result being less than `24:00:00`.
     *
     * @param int|float $timestamp
     * @return Time
     */
    public static function fromUnixTimestamp($timestamp): Time
    {
        return new Time(self::cutUnixTimestampToSec($timestamp));
    }

    /**
     * Extracts the time part from a {@link \DateTime} or {@link \DateTimeImmutable} object.
     *
     * @param \DateTimeInterface $dateTime
     * @return Time
     */
    public static function fromDateTime(\DateTimeInterface $dateTime): Time
    {
        return self::fromString($dateTime->format('H:i:s.u'));
    }
}
