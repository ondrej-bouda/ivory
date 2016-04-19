<?php
namespace Ivory\Value;

/**
 * Representation of a date and time according to the
 * {@link https://en.wikipedia.org/wiki/Proleptic_Gregorian_calendar proleptic} Gregorian calendar.
 * 
 * No timezone information is handled by this class - see {@link TimestampTz} instead.
 *
 * As in PostgreSQL, there are two special date/time values, `-infinity` and `infinity`, representing a date/time
 * respectively before or after any other date/time. There are special factory methods {@link Timestamp::minusInfinity()}
 * and {@link Timestamp::infinity()} for getting these values.
 *
 * Besides being {@link IComparable}, the {@link Timestamp} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * All the operations work correctly beyond the UNIX timestamp range bounded by 32bit integers, i.e., it is no problem
 * calculating with year 12345, for example.
 *
 * Note the date/time value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 */
class Timestamp extends DateBase
{
    /**
     * @return Timestamp date/time representing the current moment with precision to seconds
     */
    public static function now()
    {
        return new Timestamp(0, new \DateTimeImmutable('now', self::getUTCTimeZone()));
    }

    /**
     * @return Timestamp date/time representing the current moment with precision to microseconds (or, more specifically,
     *                    with the precision supported by the hosting platform - {@link microtime()} is used internally)
     */
    public static function nowMicro()
    {
        list($micro, $sec) = explode(' ', microtime());
        $microFrac = substr($micro, 1); // cut off the whole part (always a zero)
        $inputStr = gmdate('Y-m-d\TH:i:s', $sec) . $microFrac . 'UTC';
        return new Timestamp(0, new \DateTimeImmutable($inputStr));
    }

    /**
     * Creates a date/time from an ISO 8601 string.
     *
     * As ISO 8601, the input shall be formatted as, e.g., `2016-03-30T18:30:42Z`. This method also accepts a space as
     * the date/time separator instead of the `T` letter. Timezone information may be included, but is ignored by this
     * method as this class represents a date/time without timezone - use {@link TimestampTz} for that.
     *
     * Years beyond 4 digits are supported, i.e., `'12345-01-30'` is a valid input, representing a date of year 12345.
     *
     * As defined by ISO 8601, years before Christ are expected to be represented by numbers prefixed with a minus sign,
     * `0000` representing year 1 BC, `-0001` standing for year 2 BC, etc.
     *
     * Years anno Domini, i.e., the positive years, may optionally be prefixed with a plus sign.
     *
     * @param string $isoDateTimeString
     * @return Timestamp
     * @throws \InvalidArgumentException on invalid input
     */
    public static function fromISOString($isoDateTimeString)
    {
        // check out for more than 4 digits for the year - something date_create_immutable() does not handle properly
        $addYears = 0;
        $dateCreateInput = preg_replace_callback(
            '~\d{5,}(?=(?:-\d+-\d+|\d{4})(?:\s+|T)\d)~', // supports both dash-separated date/time parts and also the form without dash separators
            function ($y) use (&$addYears) {
                $res = $y[0] % 10000;
                $addYears = $y[0] - $res;
                return $res;
            },
            $isoDateTimeString,
            1
        );
        // the date_create_immutable() prefers the timezone (if any) in the input string - which we must cut off
        $dateCreateInput = preg_replace('~Z|[-+]\d{2}(?::?\d{2})?$~', '', $dateCreateInput, 1);
        if ($addYears) {
            $sgn = ($dateCreateInput[0] == '-' ? '-' : '+');
            $dateCreateInput .= " $sgn$addYears years";
        }

        $dt = date_create_immutable($dateCreateInput, self::getUTCTimeZone()); // using the procedural style as it does not throw the generic Exception
        if ($dt === false) {
            throw new \InvalidArgumentException('$isoDateString');
        }

        return new Timestamp(0, $dt);
    }

    /**
     * @param int|float $timestamp the UNIX timestamp;
     *                       besides an integer, a float is also accepted, which may contain fractional seconds;
     *                       note that a UNIX timestamp represents the number of seconds since 1970-01-01 UTC, i.e., it
     *                         corresponds to usage of PHP functions {@link gmmktime()} and {@link gmdate()} rather than
     *                         {@link mktime()} or {@link date()}
     * @return Timestamp
     */
    public static function fromTimestamp($timestamp)
    {
        $str = gmdate('Y-m-d H:i:s', (int)$timestamp);
        $dt = new Timestamp(0, new \DateTimeImmutable($str, self::getUTCTimeZone()));

        // gmdate() only accepts an integer - add the fractional part separately
        $frac = $timestamp - (int)$timestamp;
        if ($frac) {
            $dt = $dt->addSecond($frac);
        }
        return $dt;
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @return Timestamp date/time represented by the given <tt>$dateTime</tt> object, ignoring the timezone information
     */
    public static function fromDateTime(\DateTimeInterface $dateTime)
    {
        return self::fromISOString($dateTime->format('Y-m-d H:i:s.u'));
    }

    /**
     * Creates a date/time from the given year, month, day, hour, minute, and second while strictly checking for the
     * validity of the data.
     *
     * Invalid combinations of months and days, as well as hours, minutes and seconds outside their standard ranges,
     * are accepted similarly to the {@link mktime()} function.
     * E.g., `$year 2015, $month 14, $day 32, $hour 25, $minute -2, second 70` will be silently converted to
     * `2016-03-04 00:59:10`. If this is unacceptable, use the strict variant {@link Timestamp::fromPartsStrict()}
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
     * @return Timestamp
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero
     */
    public static function fromParts($year, $month, $day, $hour, $minute, $second)
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }

        $z = ($year > 0 ? $year : $year + 1);

        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31 &&
            (($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second < 61)
             ||
             ($hour == 24 && $minute == 0 && $second == 0))
            )
        {
            // works even for months without 31 days
            return self::fromISOString(sprintf(
                '%s%04d-%02d-%02d %02d:%02d:%s',
                ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, $second
            ));
        }
        else {
            return self::fromISOString(sprintf('%s%04d-01-01 00:00:00', ($z < 0 ? '-' : ''), abs($z)))
                ->addParts(0, $month - 1, $day - 1, $hour, $minute, $second);
        }
    }

    /**
     * Creates a date/time from the given year, month, day, hour, minute, and second while strictly checking for the
     * validity of the data.
     *
     * For a friendlier variant, accepting even out-of-range values (doing the adequate calculations), see
     * {@link Timestamp::fromParts()}.
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
     * @return Timestamp
     * @throws \OutOfRangeException if <tt>$month</tt> or <tt>$day</tt> are out of their valid ranges, according to the
     *                                Gregorian calendar, or if <tt>$hour</tt>, <tt>$minute</tt> or <tt>$second</tt> are
     *                                out of their ranges (0-23, 0-59 and 0-60, respectively, with the exception of time
     *                                <tt>24:00:00</tt>, which is also accepted, but silently converted to
     *                                <tt>00:00:00</tt> the next day, as well as leap seconds are converted to 0 seconds
     *                                of the next minute)
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero
     */
    public static function fromPartsStrict($year, $month, $day, $hour, $minute, $second)
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }
        if ($month < 1 || $month > 12) {
            throw new \OutOfRangeException('$month out of range');
        }
        if ($day < 1 || $day > 31) { // days in the month will be verified ex post
            throw new \OutOfRangeException('$day out of range');
        }
        if ($hour < 0 || $hour > 24 || ($hour == 24 && $minute > 0 && $second > 0)) {
            throw new \OutOfRangeException('$hour out of range');
        }
        if ($minute < 0 || $minute > 60) {
            throw new \OutOfRangeException('$minute out of range');
        }
        if ($second < 0 || $second >= 61) {
            throw new \OutOfRangeException('$second out of range');
        }

        $z = ($year > 0 ? $year : $year + 1);
        $dt = self::fromISOString(sprintf(
            '%s%04d-%02d-%02d %02d:%02d:%s',
            ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, ($second < 10 ? "0$second" : $second)
        ));
        if ($dt->format('j') == ($day + ($hour == 24 ? 1 : 0))) {
            return $dt;
        }
        else {
            throw new \OutOfRangeException('$day out of range');
        }
    }


    /**
     * @return int|null the hour part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getHour()
    {
        return ($this->inf ? null : (int)$this->dt->format('G'));
    }

    /**
     * @return int|null the minute part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getMinute()
    {
        return ($this->inf ? null : (int)$this->dt->format('i'));
    }

    /**
     * @return int|float|null the second part of the date/time, including the fractional seconds;
     *                        <tt>null</tt> iff the date/time is not finite
     */
    final public function getSecond()
    {
        if ($this->inf) {
            return null;
        }
        elseif ($this->dt->format('u')) {
            return (float)$this->dt->format('s.u');
        }
        else {
            return (int)$this->dt->format('s');
        }
    }

    final protected function getISOFormat()
    {
        return 'Y-m-d\TH:i:s' . ($this->dt->format('u') ? '.u' : '');
    }

    /**
     * Adds a given number of years, months, days, hours, minutes, and seconds to this date/time and returns the result.
     * 
     * Only affects finite date/times - an infinite date/time is returned as is.
     *
     * Note that addition of months respects the month days, and might actually change the day part. Example:
     * - adding 1 month to `2015-05-31` results in `2015-07-01` (June only has 30 days).
     *
     * Addition of years and months is done prior to addition of days, so the actual number of days in month is
     * evaluated with respect to the new month. Examples:
     * - adding 2 months and 1 day to `2015-05-31` results in `2015-08-01`,
     * - adding 1 month and 1 day to `2015-05-31` results in `2015-07-02` (June only has 30 days),
     * - adding 1 month and 1 day to `2015-02-28` results in `2015-03-29`,
     * - adding 1 year and 1 day to `2015-02-28` results in `2016-02-29`,
     * - adding 1 year and 1 day to `2016-02-28` results in `2017-03-01`.
     *
     * @param int $years
     * @param int $months
     * @param int $days
     * @param int $hours
     * @param int $minutes
     * @param int|float $seconds
     * @return Timestamp the date/time <tt>$years</tt> years, <tt>$months</tt> months, <tt>$days</tt> days,
     *                  <tt>$hours</tt> hours, <tt>$minutes</tt> minutes, and <tt>$seconds</tt> seconds after this
     *                  date/time
     */
    public function addParts($years, $months, $days, $hours, $minutes, $seconds)
    {
        return $this->addPartsImpl($years, $months, $days, $hours, $minutes, $seconds);
    }

    /**
     * Adds a given number of hours (1 by default) to this date/time and returns the result. Only affects finite dates.
     *
     * @param int $hours
     * @return static the date <tt>$hours</tt> hours after (or before, if negative) this date/time
     */
    public function addHour($hours = 1)
    {
        return $this->addPartsImpl(0, 0, 0, $hours, 0, 0);
    }

    /**
     * Adds a given number of minutes (1 by default) to this date/time and returns the result. Only affects finite
     * dates.
     *
     * @param int $minutes
     * @return static the date <tt>$minutes</tt> minutes after (or before, if negative) this date/time
     */
    public function addMinute($minutes = 1)
    {
        return $this->addPartsImpl(0, 0, 0, 0, $minutes, 0);
    }

    /**
     * Adds a given number of seconds (1 by default) to this date/time and returns the result. Only affects finite
     * dates.
     *
     * @param int|float $seconds
     * @return static the date <tt>$seconds</tt> seconds after (or before, if negative) this date/time
     */
    public function addSecond($seconds = 1)
    {
        return $this->addPartsImpl(0, 0, 0, 0, 0, $seconds);
    }

    /**
     * @return number[]|null a list of six items: year, month, day, hours, minutes, and seconds of this date, all of
     *                         which are integers except the seconds part, which might be a float if containing the
     *                         fractional part;
     *                       <tt>null</tt> iff the date/time is not finite
     */
    public function toParts()
    {
        if ($this->inf) {
            return null;
        }
        else {
            $z = (int)$this->dt->format('Y');
            return [
                ($z > 0 ? $z : $z - 1),
                (int)$this->dt->format('n'),
                (int)$this->dt->format('j'),
                (int)$this->dt->format('G'),
                (int)$this->dt->format('i'),
                $this->dt->format('s') + $this->dt->format('u'),
            ];
        }
    }
}
