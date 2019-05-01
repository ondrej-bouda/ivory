<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Representation of a date and time according to the
 * {@link https://en.wikipedia.org/wiki/Proleptic_Gregorian_calendar proleptic} Gregorian calendar.
 *
 * No timezone information is handled by this class - see {@link TimestampTz} instead.
 *
 * As in PostgreSQL, there are two special date/time values, `-infinity` and `infinity`, representing a date/time
 * respectively before or after any other date/time. There are special factory methods
 * {@link Timestamp::minusInfinity()} and {@link Timestamp::infinity()} for getting these values.
 *
 * Besides being {@link IEqualable}, the {@link Timestamp} objects may safely be compared using the `<`, `==`, and `>`
 * operators with the expected results.
 *
 * All the operations work correctly beyond the UNIX timestamp range bounded by 32bit integers, i.e., it is no problem
 * calculating with year 12345, for example.
 *
 * Note the date/time value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see https://www.postgresql.org/docs/11/datetime-units-history.html
 */
class Timestamp extends TimestampBase
{
    /**
     * @return Timestamp date/time representing the current moment, with precision to microseconds (or, more
     *                     specifically, with the precision supported by the hosting platform)
     */
    public static function now(): Timestamp
    {
        // In PHP 7.1.3 (due to bug #74258), new \DateTimeImmutable('now') had only precision up to seconds.
        if (PHP_VERSION_ID != 70103) {
            $tz = self::getUTCTimeZone();
            try {
                $datetime = new \DateTimeImmutable('now', $tz);
            } catch (\Exception $e) {
                throw new \LogicException('Date/time error', 0, $e);
            }
            return new Timestamp(0, $datetime);
        } else {
            list($micro, $sec) = explode(' ', microtime());
            $microFrac = substr($micro, 1); // cut off the whole part (always a zero)
            $inputStr = gmdate('Y-m-d\TH:i:s', $sec) . $microFrac . 'UTC';
            try {
                $dateTime = new \DateTimeImmutable($inputStr);
            } catch (\Exception $e) {
                throw new \LogicException('Date/time error', 0, $e);
            }
            return new Timestamp(0, $dateTime);
        }
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
    public static function fromISOString(string $isoDateTimeString): Timestamp
    {
        $dt = self::isoStringToDateTime($isoDateTimeString, self::getUTCTimeZone());
        return new Timestamp(0, $dt);
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @return Timestamp date/time represented by the given <tt>$dateTime</tt> object, ignoring the timezone information
     */
    public static function fromDateTime(\DateTimeInterface $dateTime): Timestamp
    {
        return self::fromISOString($dateTime->format('Y-m-d H:i:s.u'));
    }

    /**
     * Creates a date/time from the given year, month, day, hour, minute, and second.
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
    public static function fromParts(int $year, int $month, int $day, int $hour, int $minute, $second): Timestamp
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }

        $z = ($year > 0 ? $year : $year + 1);

        if (self::inRanges($month, $day, $hour, $minute, $second)) {
            // works even for months without 31 days
            return self::fromISOString(sprintf(
                '%s%04d-%02d-%02d %02d:%02d:%s',
                ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, self::floatToTwoPlaces($second)
            ));
        } else {
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
    public static function fromPartsStrict(int $year, int $month, int $day, int $hour, int $minute, $second): Timestamp
    {
        self::assertRanges($year, $month, $day, $hour, $minute, $second);
        $z = ($year > 0 ? $year : $year + 1);
        $ts = self::fromISOString(sprintf(
            '%s%04d-%02d-%02d %02d:%02d:%s',
            ($z < 0 ? '-' : ''), abs($z), $month, $day, $hour, $minute, self::floatToTwoPlaces($second)
        ));
        if ($ts->format('j') != ($day + ($hour == 24 ? 1 : 0))) {
            throw new \OutOfRangeException('$day out of range');
        }

        return $ts;
    }


    final protected function getISOFormat(): string
    {
        return 'Y-m-d\TH:i:s' . ($this->dt->format('u') ? '.u' : '');
    }

    /**
     * @return number[]|null a list of six items: year, month, day, hours, minutes, and seconds of this date/time, all
     *                         of which are integers except the seconds part, which might be a float if containing the
     *                         fractional part;
     *                       <tt>null</tt> iff the date/time is not finite
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
            ];
        }
    }
}
