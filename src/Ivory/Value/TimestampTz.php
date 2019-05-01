<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Timezone-aware representation of date and time according to the
 * {@link https://en.wikipedia.org/wiki/Proleptic_Gregorian_calendar proleptic} Gregorian calendar.
 *
 * For a timezone-unaware date/time, see {@link Timestamp}.
 *
 * As in PostgreSQL, there are two special date/time values, `-infinity` and `infinity`, representing a date/time
 * respectively before or after any other date/time. There are special factory methods
 * {@link TimestampTz::minusInfinity()} and {@link TimestampTz::infinity()} for getting these values.
 *
 * All the operations work correctly beyond the UNIX timestamp range bounded by 32bit integers, i.e., it is no problem
 * calculating with year 12345, for example.
 *
 * Note the date/time value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see https://www.postgresql.org/docs/11/datetime-units-history.html
 */
class TimestampTz extends TimestampBase
{
    /**
     * @return TimestampTz date/time representing the current moment, with precision to microseconds (or, more
     *                       specifically, with the precision supported by the hosting platform)
     */
    public static function now(): TimestampTz
    {
        // In PHP 7.1.3 (due to bug #74258), new \DateTimeImmutable('now') had only precision up to seconds.
        if (PHP_VERSION_ID != 70103) {
            try {
                $datetime = new \DateTimeImmutable('now');
            } catch (\Exception $e) {
                throw new \LogicException('Date/time error', 0, $e);
            }
            return new TimestampTz(0, $datetime);
        } else {
            list($micro, $sec) = explode(' ', microtime());
            $microFrac = substr($micro, 1); // cut off the whole part (always a zero)
            $inputStr = date('Y-m-d\TH:i:s', $sec) . $microFrac;
            try {
                $datetime = new \DateTimeImmutable($inputStr);
            } catch (\Exception $e) {
                throw new \LogicException('Date/time error', 0, $e);
            }
            return new TimestampTz(0, $datetime);
        }
    }

    /**
     * Creates a date/time from an ISO 8601 string.
     *
     * As ISO 8601, the input shall be formatted as, e.g., `2016-03-30T18:30:42Z`. This method also accepts a space as
     * the date/time separator instead of the `T` letter. Timezone information may be omitted, in which case the current
     * timezone is used. As ISO 8601 says, either `Z` or `±hh[[:]mm]` is expected as the timezone specification.
     *
     * Years beyond 4 digits are supported, i.e., `'12345-01-30'` is a valid input, representing a date of year 12345.
     *
     * As defined by ISO 8601, years before Christ are expected to be represented by numbers prefixed with a minus sign,
     * `0000` representing year 1 BC, `-0001` standing for year 2 BC, etc.
     *
     * Years anno Domini, i.e., the positive years, may optionally be prefixed with a plus sign.
     *
     * @param string $isoDateTimeString
     * @return TimestampTz
     * @throws \InvalidArgumentException on invalid input
     */
    public static function fromISOString(string $isoDateTimeString): TimestampTz
    {
        $dt = self::isoStringToDateTime($isoDateTimeString);
        return new TimestampTz(0, $dt);
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @return TimestampTz date/time represented by the given <tt>$dateTime</tt> object
     */
    public static function fromDateTime(\DateTimeInterface $dateTime): TimestampTz
    {
        if ($dateTime instanceof \DateTimeImmutable) {
            return new TimestampTz(0, $dateTime);
        } elseif ($dateTime instanceof \DateTime) {
            return new TimestampTz(0, \DateTimeImmutable::createFromMutable($dateTime));
        } else {
            // there should not be any other implementation of \DateTimeInterface, but PHP is not that predictable
            return self::fromParts(
                (int)$dateTime->format('Y'),
                (int)$dateTime->format('n'),
                (int)$dateTime->format('j'),
                (int)$dateTime->format('G'),
                (int)$dateTime->format('i'),
                (int)$dateTime->format('s') + (int)$dateTime->format('u') / 1000000,
                $dateTime->format('e')
            );
        }
    }

    /** @noinspection PhpTooManyParametersInspection */
    /**
     * Creates a date/time from the given year, month, day, hour, minute, second, and timezone.
     *
     * Invalid combinations of months and days, as well as hours, minutes and seconds outside their standard ranges,
     * are accepted similarly to the {@link mktime()} function.
     * E.g., `$year 2015, $month 14, $day 32, $hour 25, $minute -2, second 70` will be silently converted to
     * `2016-03-04 00:59:10`. If this is unacceptable, use the strict variant {@link TimestampTz::fromPartsStrict()}
     * instead.
     *
     * Years before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
     *
     * Note that, in the Gregorian calendar, there is no year 0. Thus, `$year == 0` will be rejected with an
     * `\InvalidArgumentException`.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $hour
     * @param int $minute
     * @param int|float $second
     * @param \DateTimeZone|string|int $timezone either the \DateTimeZone object, or
     *                                             a string containing the timezone name or its abbreviation (e.g.,
     *                                             <tt>Europe/Prague</tt> or <tt>CEST</tt>) or ISO-style offset from GMT
     *                                             (e.g., <tt>+02:00</tt> or <tt>+0200</tt> or <tt>+02</tt>), or
     *                                             an integer specifying the offset from GMT in seconds
     * @return TimestampTz
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero or <tt>$timezone</tt> is not recognized by PHP
     */
    public static function fromParts(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        $second,
        $timezone
    ): TimestampTz {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }

        $tz = self::parseTimezone($timezone);
        $z = ($year > 0 ? $year : $year + 1);

        if (self::inRanges($month, $day, $hour, $minute, $second)) {
            // works even for months without 31 days
            $dt = self::isoStringToDateTime(
                sprintf(
                    '%s%04d-%02d-%02d %02d:%02d:%s',
                    ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, self::floatToTwoPlaces($second)
                ),
                $tz
            );
            return new TimestampTz(0, $dt);
        } else {
            $dt = self::isoStringToDateTime(
                sprintf('%s%04d-01-01 00:00:00', ($z < 0 ? '-' : ''), abs($z)),
                $tz
            );
            return (new TimestampTz(0, $dt))
                ->addParts(0, $month - 1, $day - 1, $hour, $minute, $second);
        }
    }

    /** @noinspection PhpTooManyParametersInspection */
    /**
     * Creates a date/time from the given year, month, day, hour, minute, second, and timezone while strictly checking
     * for the validity of the data.
     *
     * For a friendlier variant, accepting even out-of-range values (doing the adequate calculations), see
     * {@link TimestampTz::fromParts()}.
     *
     * Years before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
     *
     * Note that, in the Gregorian calendar, there is no year 0. Thus, `$year == 0` will be rejected with an
     * `\InvalidArgumentException`.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $hour
     * @param int $minute
     * @param int|float $second
     * @param \DateTimeZone|string|int $timezone either the \DateTimeZone object, or
     *                                             a string containing the timezone name or its abbreviation (e.g.,
     *                                             <tt>Europe/Prague</tt> or <tt>CEST</tt>) or ISO-style offset from GMT
     *                                             (e.g., <tt>+02:00</tt> or <tt>+0200</tt> or <tt>+02</tt>), or
     *                                             an integer specifying the offset from GMT in seconds
     * @return TimestampTz
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero or <tt>$timezone</tt> is not recognized by PHP
     */
    public static function fromPartsStrict(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        $second,
        $timezone
    ): TimestampTz {
        self::assertRanges($year, $month, $day, $hour, $minute, $second);
        $tz = self::parseTimezone($timezone);
        $z = ($year > 0 ? $year : $year + 1);

        $dt = self::isoStringToDateTime(
            sprintf(
                '%s%04d-%02d-%02d %02d:%02d:%s',
                ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, self::floatToTwoPlaces($second)
            ),
            $tz
        );
        if ($dt->format('j') != ($day + ($hour == 24 ? 1 : 0))) {
            throw new \OutOfRangeException('$day out of range');
        }

        return new TimestampTz(0, $dt);
    }

    private static function parseTimezone($timezone): \DateTimeZone
    {
        if ($timezone instanceof \DateTimeZone) {
            return $timezone;
        }

        if (filter_var($timezone, FILTER_VALIDATE_INT) !== false) {
            $tzSpec = ($timezone >= 0 ? '+' : '-') . gmdate('H:i', abs($timezone));
        } elseif (preg_match('~^([^:]+:\d+):\d+$~', $timezone, $m)) {
            $tzSpec = $m[1];
            $msg = "PHP's DateTimeZone is unable to represent GMT offsets with precision to seconds. "
                . "Cutting '$timezone' to '$tzSpec'";
            trigger_error($msg, E_USER_WARNING);
        } else {
            $tzSpec = $timezone;
        }

        try {
            return new \DateTimeZone($tzSpec);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('$timezone', 0, $e);
        }
    }

    final protected function getISOFormat(): string
    {
        return 'Y-m-d\TH:i:s' . ($this->dt->format('u') ? '.u' : '') . 'O';
    }

    /**
     * @return string the timezone offset of this time from the Greenwich Mean Time formatted according to ISO 8601
     *                  using no delimiter, e.g., <tt>+0200</tt> or <tt>-0830</tt>
     */
    public function getOffsetISOString(): string
    {
        return $this->dt->format('O');
    }

    /**
     * @return mixed[]|null a list of seven items: year, month, day, hours, minutes, seconds, and timezone of this
     *                       date/time, all of which are integers except the seconds part, which might be a float if
     *                       containing the fractional part, and the timezone part, which is a {@link \DateTimeZone}
     *                       object;
     *                     <tt>null</tt> iff the date/time is not finite
     */
    public function toParts(): ?array
    {
        if ($this->inf) {
            return null;
        } else {
            $y = (int)$this->dt->format('Y');
            $u = $this->dt->format('u');
            return [
                ($y > 0 ? $y : $y - 1),
                (int)$this->dt->format('n'),
                (int)$this->dt->format('j'),
                (int)$this->dt->format('G'),
                (int)$this->dt->format('i'),
                (int)$this->dt->format('s') + ($u ? $u / 1000000 : 0),
                $this->dt->getTimezone(),
            ];
        }
    }
}
