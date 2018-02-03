<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Value\Alg\ComparableWithPhpOperators;
use Ivory\Value\Alg\IComparable;

/**
 * Common base for time representations.
 *
 * @internal Only for the purpose of Ivory itself.
 */
abstract class TimeBase implements IComparable
{
    use ComparableWithPhpOperators;

    /** Number of decimal digits of precision in the fractional seconds part. */
    const PRECISION = 6;

    /** @var int|float */
    protected $sec;


    /**
     * @param int $hour
     * @param int $minute
     * @param int|float $second
     * @return int|float
     * @throws \OutOfRangeException
     */
    protected static function partsToSec(int $hour, int $minute, $second)
    {
        $s = $hour * 60 * 60 + $minute * 60 + $second;

        if ($s < 0) {
            throw new \OutOfRangeException('The resulting time underruns 00:00:00');
        } elseif ($s > 24 * 60 * 60) {
            throw new \OutOfRangeException('The resulting time exceeds 24:00:00');
        } else {
            return $s;
        }
    }

    /**
     * @param int $hour
     * @param int $minute
     * @param int|float $second
     * @return int|float
     * @throws \OutOfRangeException
     */
    protected static function partsToSecStrict(int $hour, int $minute, $second)
    {
        if ($hour == 24) {
            if ($minute > 0 || $second > 0) {
                throw new \OutOfRangeException('with hour 24, the minutes and seconds must be zero');
            }
        } elseif ($hour < 0 || $hour > 24) {
            throw new \OutOfRangeException('hours');
        }

        if ($minute < 0 || $minute > 59) {
            throw new \OutOfRangeException('minutes');
        }

        if ($second < 0 || $second >= 61) {
            throw new \OutOfRangeException('seconds');
        }

        return $hour * 60 * 60 + $minute * 60 + $second;
    }

    /**
     * @param int|float $timestamp
     * @return int|float
     */
    protected static function cutUnixTimestampToSec($timestamp)
    {
        if ($timestamp == 24 * 60 * 60) {
            return $timestamp;
        }

        $dayRes = (int)($timestamp - ($timestamp % (24 * 60 * 60)));
        $sec = $timestamp - $dayRes;
        if ($sec < 0) {
            $sec += 24 * 60 * 60;
        }

        return $sec;
    }

    /**
     * @internal Only for the purpose of Ivory itself.
     * @param int|float $sec
     */
    protected function __construct($sec)
    {
        $this->sec = $sec;
    }

    /**
     * @return int the hours part of the time (0-24)
     */
    public function getHours(): int
    {
        return (int)($this->sec / (60 * 60));
    }

    /**
     * @return int the minutes part of the time (0-59)
     */
    public function getMinutes(): int
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
     * @param Date|string|null $date the date for the resulting timestamp;
     *                               besides a {@link Date} object, an ISO date string is accepted - see
     *                                 {@link Date::fromISOString()};
     *                               the given date (if any) must be finite;
     *                               if not given the time on 1970-01-01 is returned, which is effectively the amount of
     *                                 time, in seconds, between the time this object represents and <tt>00:00:00</tt>
     * @return float|int the UNIX timestamp of this time on the given day
     * @throws \InvalidArgumentException if the date is infinite or if the <tt>$date</tt> string is not a valid ISO date
     *                                     string
     */
    public function toUnixTimestamp($date = null)
    {
        if ($date === null) {
            return $this->sec;
        } else {
            if (!$date instanceof Date) {
                $date = Date::fromISOString($date);
            }
            $dayTs = $date->toUnixTimestamp();
            if ($dayTs !== null) {
                return $dayTs + $this->sec;
            } else {
                throw new \InvalidArgumentException('infinite date');
            }
        }
    }

    /**
     * @param string $timeFmt the format string as accepted by {@link date()}
     * @return string the time formatted according to <tt>$timeFmt</tt>
     */
    public function format(string $timeFmt): string
    {
        $ts = $this->toUnixTimestamp();

        // microseconds are not supported by gmdate(), and constructing a new \DateTime object would be overkill
        if (strpos($timeFmt, 'u') !== false) {
            $frac = round($ts - (int)$ts, self::PRECISION);
            $fracPart = ($frac ? substr((string)$frac, 2) : '0'); // cut off the leading "0." for non-zero fractional seconds
            $fracStr = str_pad($fracPart, 6, '0', STR_PAD_RIGHT);

            $re = '~
                   (?<!\\\\)            # not prefixed with a backslash
                   ((?:\\\\\\\\)*)      # any number of pairs of backslashes, each meaning a single literal backslash
                   u                    # the microseconds format character to be replaced
                   ~x';
            $timeFmt = preg_replace($re, '${1}' . $fracStr, $timeFmt);
        }

        return gmdate($timeFmt, (int)$ts);
    }

    /**
     * @return string the ISO representation of this time, in format <tt>HH:MM:SS[.p]</tt>;
     *                the fractional seconds part is only used if non-zero
     */
    public function toString(): string
    {
        $frac = round($this->sec - (int)$this->sec, self::PRECISION);
        return sprintf(
            '%02d:%02d:%02d%s',
            $this->getHours(), $this->getMinutes(), $this->getSeconds(),
            ($frac ? substr((string)$frac, 1) : '') // cut off the leading "0" for non-zero fractional seconds
        );
    }
}
