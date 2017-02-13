<?php
namespace Ivory\Value;

/**
 * Representation of a date according to the
 * {@link https://en.wikipedia.org/wiki/Proleptic_Gregorian_calendar proleptic} Gregorian calendar.
 *
 * As in PostgreSQL, there are two special dates, `-infinity` and `infinity`, representing a date respectively before or
 * after any other date. There are special factory methods {@link Date::minusInfinity()} and {@link Date::infinity()}
 * for getting these values.
 *
 * Besides being {@link IComparable}, the {@link Date} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * All the operations work correctly beyond the UNIX timestamp range bounded by 32bit integers, i.e., it is no problem
 * calculating with year 12345, for example.
 *
 * Note the date value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 */
class Date extends DateBase
{
    /**
     * @return Date date representing the current day
     */
    public static function today()
    {
        return new Date(0, new \DateTimeImmutable('today', self::getUTCTimeZone()));
    }

    /**
     * @return Date date representing the next day
     */
    public static function tomorrow()
    {
        return new Date(0, new \DateTimeImmutable('tomorrow', self::getUTCTimeZone()));
    }

    /**
     * @return Date date representing the previous day
     */
    public static function yesterday()
    {
        return new Date(0, new \DateTimeImmutable('yesterday', self::getUTCTimeZone()));
    }

    /**
     * Creates a date from an ISO 8601 string, i.e., formatted as `YYYY-MM-DD`.
     *
     * Years beyond 4 digits are supported, i.e., `'12345-01-30'` is a valid input, representing a date of year 12345.
     *
     * As defined by ISO 8601, years before Christ are expected to be represented by numbers prefixed with a minus sign,
     * `0000` representing year 1 BC, `-0001` standing for year 2 BC, etc.
     *
     * Years anno Domini, i.e., the positive years, may optionally be prefixed with a plus sign.
     *
     * @param string $isoDateString
     * @return Date
     * @throws \InvalidArgumentException on invalid input
     */
    public static function fromISOString($isoDateString)
    {
        // check out for more than 4 digits for the year - something date_create_immutable() does not handle properly
        $addYears = 0;
        $dateCreateInput = preg_replace_callback(
            '~\d{5,}(?=-|\d{4})~', // supports both dash-separated date parts and also the form without dash separators
            function ($y) use (&$addYears) {
                $res = $y[0] % 10000;
                $addYears = $y[0] - $res;
                return $res;
            },
            $isoDateString,
            1
        );
        if ($addYears) {
            $sgn = ($dateCreateInput[0] == '-' ? '-' : '+');
            $dateCreateInput .= " $sgn$addYears years";
        }

        $dt = date_create_immutable($dateCreateInput, self::getUTCTimeZone()); // using the procedural style as it does not throw the generic Exception
        if ($dt === false) {
            throw new \InvalidArgumentException('$isoDateString');
        }

        return new Date(0, $dt);
    }

    /**
     * @param int $timestamp the UNIX timestamp;
     *                       just the date part is used, other information is ignored;
     *                       note that a UNIX timestamp represents the number of seconds since 1970-01-01 UTC, i.e., it
     *                         corresponds to usage of PHP functions {@link gmmktime()} and {@link gmdate()} rather than
     *                         {@link mktime()} or {@link date()}
     * @return Date the date of the given timestamp
     */
    public static function fromUnixTimestamp($timestamp)
    {
        return new Date(0, new \DateTimeImmutable(gmdate('Y-m-d', $timestamp), self::getUTCTimeZone()));
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @return Date date represented by the date part of the given <tt>$dateTime</tt> object
     */
    public static function fromDateTime(\DateTimeInterface $dateTime)
    {
        return self::fromISOString($dateTime->format('Y-m-d'));
    }

    /**
     * Creates a date from the given year, month, and day.
     *
     * Invalid combinations of months and days are accepted similarly to the {@link mktime()} function.
     * E.g., `$year 2015, $month 14, $day 32` will be silently converted to `2016-03-03`. If this is unacceptable, use
     * the strict variant {@link Date::fromPartsStrict()} instead.
     *
     * Years before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
     *
     * Note that, in the Gregorian calendar, there is no year 0. Thus, `$year == 0` will be rejected with an
     * `\InvalidArgumentException`, which is the only case an exception is thrown by this method.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return Date
     * @throws \InvalidArgumentException iff <tt>$year</tt> is zero
     */
    public static function fromParts($year, $month, $day)
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }

        $z = ($year > 0 ? $year : $year + 1);

        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) { // works even for months without 31 days
            return self::fromISOString(sprintf('%s%04d-%02d-%02d', ($z < 0 ? '-' : ''), abs($z), $month, $day));
        } else {
            return self::fromISOString(sprintf('%s%04d-01-01', ($z < 0 ? '-' : ''), abs($z)))
                ->addParts(0, $month - 1, $day - 1);
        }
    }

    /**
     * Creates a date from the given year, month, and day while strictly checking for the validity of the data.
     *
     * For a friendlier variant, accepting even out-of-range values (doing the adequate calculations), see
     * {@link Date::fromParts()}.
     *
     * Years before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
     *
     * Note that, in the Gregorian calendar, there is no year 0. Thus, `$year == 0` will be rejected with an
     * `\InvalidArgumentException`.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return Date
     * @throws \OutOfRangeException if <tt>$month</tt> or <tt>$day</tt> are out of their valid ranges, according to the
     *                                Gregorian calendar
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero
     */
    public static function fromPartsStrict($year, $month, $day)
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

        $z = ($year > 0 ? $year : $year + 1);
        $date = self::fromISOString(sprintf('%s%04d-%02d-%02d', ($z < 0 ? '-' : ''), abs($z), $month, $day));
        if ($date->format('j') == $day) {
            return $date;
        } else {
            throw new \OutOfRangeException('$day out of range');
        }
    }


    final protected function getISOFormat()
    {
        return 'Y-m-d';
    }

    /**
     * Adds a given number of years, months, and days to this date and returns the result.
     *
     * Only affects finite dates - an infinite date is returned as is.
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
     * @return Date the date <tt>$years</tt> years, <tt>$months</tt> months and <tt>$days</tt> days after this date
     */
    public function addParts($years, $months, $days)
    {
        return $this->addPartsImpl($years, $months, $days, 0, 0, 0);
    }

    /**
     * @return int[]|null a triple of year, month, and day of this date;
     *                    <tt>null</tt> iff the date is not finite
     */
    public function toParts()
    {
        if ($this->inf) {
            return null;
        } else {
            $z = (int)$this->dt->format('Y');
            return [($z > 0 ? $z : $z - 1), (int)$this->dt->format('n'), (int)$this->dt->format('j')];
        }
    }
}
