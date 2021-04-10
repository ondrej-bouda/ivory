<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * @internal Only for the purpose of Ivory itself.
 */
abstract class TimestampBase extends DateBase
{
    /**
     * @param int|float $timestamp the UNIX timestamp;
     *                       besides an integer, a float is also accepted, which may contain fractional seconds;
     *                       note that a UNIX timestamp represents the number of seconds since 1970-01-01 UTC, i.e., it
     *                         corresponds to usage of PHP functions {@link gmmktime()} and {@link gmdate()} rather than
     *                         {@link mktime()} or {@link date()}
     * @return static
     */
    public static function fromUnixTimestamp($timestamp)
    {
        $str = gmdate('Y-m-d H:i:s', (int)$timestamp);
        $tz = self::getUTCTimeZone();
        try {
            $datetime = new \DateTimeImmutable($str, $tz);
        } catch (\Exception $e) {
            throw new \LogicException('Date/time error', 0, $e);
        }
        $timestamp = new static(0, $datetime);

        // gmdate() only accepts an integer - add the fractional part separately
        $frac = $timestamp - (int)$timestamp;
        if ($frac) {
            $timestamp = $timestamp->addSecond($frac);
        }
        return $timestamp;
    }

    protected static function isoStringToDateTime(
        string $isoDateTimeString,
        ?\DateTimeZone $forcedTimezone = null
    ): \DateTimeImmutable {
        // check out for more than 4 digits for the year - something date_create_immutable() does not handle properly
        $addYears = 0;
        $dateCreateInput = preg_replace_callback(
            '~\d{5,}(?=(?:-\d+-\d+|\d{4})(?:\s+|T)\d)~', // supports both dash-separated date/time parts and also the
                                                         // form without dash separators
            function ($y) use (&$addYears) {
                $res = $y[0] % 10000;
                $addYears = $y[0] - $res;
                return $res;
            },
            $isoDateTimeString,
            1
        );

        if ($forcedTimezone !== null) {
            // the date_create_immutable() prefers the timezone (if any) in the input string - which we must cut off
            $dateCreateInput = preg_replace('~Z|[-+]\d{2}(?::?\d{2})?$~', '', $dateCreateInput, 1);
        }

        if ($addYears) {
            $sgn = ($dateCreateInput[0] == '-' ? '-' : '+');
            $dateCreateInput .= " $sgn$addYears years";
        }

        // using the procedural style as it does not throw the generic \Exception
        $dt = date_create_immutable($dateCreateInput, $forcedTimezone);
        if ($dt === false) {
            throw new \InvalidArgumentException('$isoDateString');
        }

        return $dt;
    }

    protected static function floatToTwoPlaces($float): string
    {
        if ($float >= 10) {
            return (string)$float;
        } else {
            return '0' . (float)$float;
        }
    }

    protected static function inRanges(int $month, int $day, int $hour, int $minute, $second): bool
    {
        return (
            $month >= 1 && $month <= 12 &&
            $day >= 1 && $day <= 31 &&
            (
                ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second < 61)
                ||
                ($hour == 24 && $minute == 0 && $second == 0)
            )
        );
    }

    protected static function assertRanges(int $year, int $month, int $day, int $hour, int $minute, $second): void
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }
        if ($month < 1 || $month > 12) {
            throw new \OutOfRangeException('$month out of range');
        }
        if ($day < 1 || $day > 31) { // days in the month shall be verified ex post
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
    }


    /**
     * @return int|null the hour part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getHour(): ?int
    {
        return ($this->inf ? null : (int)$this->dt->format('G'));
    }

    /**
     * @return int|null the minute part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getMinute(): ?int
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
        } elseif ($this->dt->format('u')) {
            return (float)$this->dt->format('s.u');
        } else {
            return (int)$this->dt->format('s');
        }
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
     * @return static the date/time <tt>$years</tt> years, <tt>$months</tt> months, <tt>$days</tt> days,
     *                  <tt>$hours</tt> hours, <tt>$minutes</tt> minutes, and <tt>$seconds</tt> seconds after this
     *                  date/time
     */
    public function addParts(int $years, int $months, int $days, int $hours, int $minutes, $seconds)
    {
        return $this->addPartsImpl($years, $months, $days, $hours, $minutes, $seconds);
    }

    /**
     * Adds a given number of hours (1 by default) to this date/time and returns the result. Only affects finite dates.
     *
     * @param int $hours
     * @return static the date <tt>$hours</tt> hours after (or before, if negative) this date/time
     */
    public function addHour(int $hours = 1)
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
    public function addMinute(int $minutes = 1)
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
}
