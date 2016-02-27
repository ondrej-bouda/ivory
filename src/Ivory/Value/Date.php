<?php
namespace Ivory\Value;

use Ivory\Utils\IComparable;

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
 * calculating with year 9000, for example.
 *
 * Note the date value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 */
class Date implements IComparable
{
    // TODO: consider implementing all the functionality using \DateTimeImmutable; operations could be simpler and faster, too

    // NOTE: the order of the fields is important for the `<` and `>` operators to work correctly
    private $year;
    private $month;
    private $day;

    /**
     * @return Date date representing the current day
     */
    public static function today()
    {
        return new Date(...explode('-', date('Y-n-j')));
    }

    /**
     * @return Date date representing the next day
     */
    public static function tomorrow()
    {
        return new Date(...explode('-', date('Y-n-j', strtotime('+1 day'))));
    }

    /**
     * @return Date date representing the previous day
     */
    public static function yesterday()
    {
        return new Date(...explode('-', date('Y-n-j', strtotime('-1 day'))));
    }

    /**
     * @return Date the special `infinity` date, taking part after any other date
     */
    public static function infinity()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new Date(PHP_INT_MAX, 12, 32);
        }
        return $inst;
    }

    /**
     * @return Date the special `-infinity` date, taking part before any other date
     */
    public static function minusInfinity()
    {
        static $inst = null;
        if ($inst === null) {
            $intMin = (PHP_MAJOR_VERSION >= 7 ? PHP_INT_MIN : -PHP_INT_MAX); // PHP 7: refactor out
            $inst = new Date($intMin, 0, 0);
        }
        return $inst;
    }

    /**
     * @param string $dateString any string {@link strtotime()} accepts, especially the ISO format `YYYY-MM-DD`
     * @return Date the date parsed from <tt>$dateString</tt> using {@link strtotime()}
     * @throws \InvalidArgumentException on invalid input
     */
    public static function fromString($dateString)
    {
        $ts = strtotime($dateString); // FIXME: values outside of the timestamp range will get scrambled
        if ($ts === false) {
            throw new \InvalidArgumentException('$dateString');
        }
        // TODO
    }

    /**
     * @param int $timestamp the UNIX timestamp
     * @return Date the date of the given timestamp
     */
    public static function fromTimestamp($timestamp)
    {
        return new Date(date('Y', $timestamp), date('n', $timestamp), date('j', $timestamp));
    }

    /**
     * @param \DateTimeInterface $dateTime
     * @return Date date represented by the date part of the given <tt>$dateTime</tt> object
     */
    public static function fromDateTime(\DateTimeInterface $dateTime)
    {
        return self::fromString($dateTime->format('Y-m-d'));
    }

    /**
     * Creates a date from the given year, month, and day.
     *
     * Invalid combinations of months and days are accepted similarly to the {@link mktime()} function.
     * E.g., `$year 2015, $month 14, $day 32` will be silently converted to `2016-03-03`. If this is unacceptable, use
     * the strict variant {@link Date::fromComponentsStrict()} instead.
     *
     * Years Before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
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
    public static function fromComponents($year, $month, $day)
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }

        $year += ($month - 1 - (($month - 1) % 12)) / 12;
        $month = ($month - 1) % 12 + 1;
        if ($year <= 0) {
            $year--; // skip year 0
        }
        if ($day >= 1 && $day <= 28) { // day 28 is valid for any month in any year
            return new Date($year, $month, $day);
        }
        else {
            return (new Date($year, $month, 1))->addDay($day - 1);
        }
    }

    /**
     * Creates a date from the given year, month, and day while strictly checking for the validity of the data.
     *
     * For a friendlier variant, accepting even out-of-range values (doing the adequate calculations), see
     * {@link Date::fromComponents()}.
     *
     * Years Before Christ shall be represented by negative numbers. E.g., year 42 BC shall be given as -42.
     *
     * Note that, in the Gregorian calendar, there is no year 0. Thus, `$year == 0` will be rejected with an
     * `\InvalidArgumentException`, which is the only case an exception is thrown by this method.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return Date
     * @throws \OutOfRangeException if <tt>$month</tt> or <tt>$day</tt> are out of their valid ranges, according to the
     *                                Gregorian calendar
     * @throws \InvalidArgumentException if <tt>$year</tt> is zero
     */
    public static function fromComponentsStrict($year, $month, $day)
    {
        if ($year == 0) {
            throw new \InvalidArgumentException('$year zero is undefined');
        }
        if ($month < 1 || $month > 12) {
            throw new \OutOfRangeException('$month out of range');
        }
        // TODO: verify $day

        return new Date($year, $month, $day);
    }


    /**
     * Assumes the values are in their ranges valid for the Gregorian calendar. It's the callers responsibility if they
     * are not.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     */
    private function __construct($year, $month, $day)
    {
        $this->year = $year;
        $this->month = $month;
        $this->day = $day;
    }


    /**
     * @return int the year part of the date; years Before Christ are negative
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * @return int the month part of the date
     */
    public function getMonth()
    {
        return $this->month;
    }

    /**
     * @return int the day part of the date
     */
    public function getDay()
    {
        return $this->day;
    }

    /**
     * @param string $dateFmt the format string as accepted by {@link date()}
     * @return string
     */
    public function format($dateFmt)
    {
        // TODO
    }

    /**
     * @param int $days
     * @return Date the date <tt>$days</tt> days after (or before, if negative) this date
     */
    public function addDay($days = 1)
    {
        return $this->addComponents(0, 0, $days);
    }

    /**
     * @param int $years
     * @param int $months
     * @param int $days
     * @return Date the date <tt>$years</tt> years, <tt>$months</tt> months and <tt>$days</tt> days after this date
     */
    public function addComponents($years, $months, $days)
    {
        throw new \Ivory\Exception\NotImplementedException(); // TODO
    }

    //region IComparable

    public function equals($object)
    {
        if ($object === null) {
            return null;
        }
        return ($this == $object);
    }

    //endregion
}
