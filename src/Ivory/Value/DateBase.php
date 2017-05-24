<?php
namespace Ivory\Value;

use Ivory\Utils\EqualableWithPhpOperators;
use Ivory\Utils\IEqualable;

/**
 * Common base for date and date/time representations.
 *
 * @internal Only for the purpose of Ivory itself.
 */
abstract class DateBase implements IEqualable
{
    use EqualableWithPhpOperators;

    // NOTE: the order of the fields is important for the `<` and `>` operators to work correctly
    /** @var int -1, 0, or 1 if this date is <tt>-infinity</tt>, finite, or <tt>infinity</tt> */
    protected $inf;
    /** @var \DateTimeImmutable; the UTC timezone is always used */
    protected $dt;


    /**
     * @return static the special `infinity` date, taking part after any other date
     */
    public static function infinity()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new static(1, null);
        }
        return $inst;
    }

    /**
     * @return static the special `-infinity` date, taking part before any other date
     */
    public static function minusInfinity()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new static(-1, null);
        }
        return $inst;
    }

    protected static function getUTCTimeZone()
    {
        static $utc = null;
        if ($utc === null) {
            $utc = new \DateTimeZone('UTC');
        }
        return $utc;
    }


    /**
     * @internal Only for the purpose of Ivory itself.
     */
    final protected function __construct($inf, \DateTimeImmutable $dt = null)
    {
        $this->inf = $inf;
        $this->dt = $dt;

        // there was a bug in some PHP versions that \DateTime objects comparison did not take fractional seconds into account
        if (PHP_VERSION_ID < 50619 || (PHP_VERSION_ID >= 70000 && PHP_VERSION_ID < 70004)) {
            $this->frac = ($dt !== null ? $dt->format('u') : null);
        }
    }

    /**
     * @return bool <tt>true</tt> if this is a finite date/time,
     *              <tt>false</tt> if <tt>infinity</tt> or <tt>-infinity</tt>
     */
    final public function isFinite(): bool
    {
        return !$this->inf;
    }

    /**
     * @return int|null the year part of the date/time;
     *                  years before Christ are negative, starting from -1 for year 1 BC, -2 for year 2 BC, etc.;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getYear()
    {
        $z = $this->getZeroBasedYear();
        if ($z > 0 || $z === null) {
            return $z;
        } else {
            return $z - 1;
        }
    }

    /**
     * Returns the year from this date/time, interpreting years before Christ as non-positive numbers: 0 for year 1 BC,
     * -1 for year 2 BC, etc. This is the number appearing as year in the ISO 8601 date string format.
     *
     * @return int|null the year of the date/time, basing year 1 BC as zero;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getZeroBasedYear() // NOTE: not named getISOYear() to avoid confusion with EXTRACT(ISOYEAR FROM ...)
    {
        return ($this->inf ? null : (int)$this->dt->format('Y'));
    }

    /**
     * @return int|null the month part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getMonth()
    {
        return ($this->inf ? null : (int)$this->dt->format('n'));
    }

    /**
     * @return int|null the day part of the date/time;
     *                  <tt>null</tt> iff the date/time is not finite
     */
    final public function getDay()
    {
        return ($this->inf ? null : (int)$this->dt->format('j'));
    }

    /**
     * @param string $dateFmt the format string as accepted by {@link date()}
     * @return string|null the date/time formatted according to <tt>$dateFmt</tt>;
     *                     <tt>null</tt> iff the date/time is not finite
     */
    final public function format(string $dateFmt)
    {
        if ($this->inf) {
            return null;
        } else {
            return $this->dt->format($dateFmt);
        }
    }


    /**
     * @return string|null the date/time represented as an ISO 8601 string;
     *                     years before Christ represented are using the minus prefix, year 1 BC as <tt>0000</tt>;
     *                     <tt>null</tt> iff the date/time is not finite
     */
    public function toISOString()
    {
        return ($this->inf ? null : $this->dt->format($this->getISOFormat()));
    }

    /**
     * @return string date format as defined by ISO 8601
     */
    abstract protected function getISOFormat(): string;

    /**
     * @return int|null the date/time represented as the UNIX timestamp;
     *                  <tt>null</tt> iff the date is not finite;
     *                  note that a UNIX timestamp represents the number of seconds since 1970-01-01 UTC, i.e., it
     *                    corresponds to usage of PHP functions {@link gmmktime()} and {@link gmdate()} rather than
     *                    {@link mktime()} or {@link date()}
     */
    public function toUnixTimestamp()
    {
        return ($this->inf ? null : $this->dt->getTimestamp());
    }

    /**
     * @param \DateTimeZone $timezone timezone to create the {@link \DateTime} object with
     * @return \DateTime|null the date/time represented as a {@link \DateTime} object;
     *                        <tt>null</tt> iff the date/time is not finite
     */
    public function toDateTime(\DateTimeZone $timezone = null)
    {
        return ($this->inf ? null : new \DateTime($this->toISOString(), $timezone)); // OPT: \DateTime::createFromFormat() is supposed to be twice as fast as new \DateTime()
    }

    /**
     * @param \DateTimeZone $timezone timezone to create the {@link \DateTime} object with
     * @return \DateTime|null the date/time represented as a {@link \DateTimeImmutable} object;
     *                        <tt>null</tt> iff the date/time is not finite
     */
    public function toDateTimeImmutable(\DateTimeZone $timezone = null)
    {
        if ($this->inf) {
            return null;
        } else {
            if ($timezone === $this->dt->getTimezone()) {
                return $this->dt;
            } else {
                return new \DateTimeImmutable($this->toISOString(), $timezone);
            }
        }
    }

    /**
     * Adds a given number of days (1 by default) to this date and returns the result. Only affects finite dates.
     *
     * @param int $days
     * @return static the date/time <tt>$days</tt> days after (or before, if negative) this date/time
     */
    public function addDay($days = 1)
    {
        return $this->addPartsImpl(0, 0, $days, 0, 0, 0);
    }

    /**
     * Adds a given number of months (1 by default) to this date and returns the result. Only affects finite dates.
     *
     * Note that addition of months respects the month days, and might actually change the day part. Example:
     * - adding 1 month to `2015-05-31` results in `2015-07-01` (June only has 30 days).
     *
     * @param int $months
     * @return static the date/time <tt>$months</tt> months after (or before, if negative) this date/time
     */
    public function addMonth($months = 1)
    {
        return $this->addPartsImpl(0, $months, 0, 0, 0, 0);
    }

    /**
     * Adds a given number of years (1 by default) to this date/time and returns the result. Only affects finite dates.
     *
     * @param int $years
     * @return static the date/time <tt>$years</tt> years after (or before, if negative) this date/time
     */
    public function addYear($years = 1)
    {
        return $this->addPartsImpl($years, 0, 0, 0, 0, 0);
    }


    final protected function addPartsImpl($years, $months, $days, $hours, $minutes, $seconds)
    {
        if ($this->inf) {
            return $this;
        }

        $yp = ($years >= 0 ? '+' : '');
        $mp = ($months >= 0 ? '+' : '');
        $dp = ($days >= 0 ? '+' : '');
        $hp = ($hours >= 0 ? '+' : '');
        $ip = ($minutes >= 0 ? '+' : '');

        $wholeSec = (int)$seconds;
        $fracSec = $seconds - $wholeSec;
        if ($fracSec != 0) {
            // in current PHP, there is no method for modifying the microseconds of a date/time - we must do it by hand
            $resFracSec = $fracSec + $this->dt->format('.u');
            if ($resFracSec < 0) {
                $resFracSec++;
                $wholeSec--;
            } elseif ($resFracSec >= 1) {
                $resFracSec--;
                $wholeSec++;
            }
        }
        $sp = ($wholeSec >= 0 ? '+' : '');

        $mod = "$yp$years years $mp$months months $dp$days days $hp$hours hours $ip$minutes minutes $sp$wholeSec seconds";
        $dt = $this->dt->modify($mod);

        if ($fracSec != 0) {
            /** @noinspection PhpUndefinedVariableInspection */
            $resFracSecStr = substr($resFracSec, 2); // cut off the leading '0.'
            $dt = new \DateTimeImmutable($dt->format('Y-m-d H:i:s.') . $resFracSecStr, self::getUTCTimeZone());
        }

        return new static(0, $dt);
    }
}
