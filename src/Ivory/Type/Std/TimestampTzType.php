<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\Config\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\ConnectionDependentBaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\TimestampTz;

/**
 * Timezone-aware date and time, counted according to the Gregorian calendar, even in years before that calendar was
 * introduced.
 *
 * Represented as a {@link \Ivory\Value\TimestampTz} object.
 *
 * The format of recognized values is the same as for {@link \Ivory\Value\Timestamp}. All possible
 * {@link https://www.postgresql.org/docs/11/runtime-config-client.html#GUC-DATESTYLE DateStyle} settings are
 * supported.
 *
 * @see https://www.postgresql.org/docs/11/datatype-datetime.html
 * @see https://www.postgresql.org/docs/11/datetime-units-history.html
 * @see https://www.postgresql.org/docs/11/runtime-config-client.html#GUC-DATESTYLE
 */
class TimestampTzType extends ConnectionDependentBaseType implements ITotallyOrderedType
{
    /** @var ConnConfigValueRetriever */
    private $dateStyleRetriever;
    /** @var ConnConfigValueRetriever */
    private $localMeanTimeZoneRetriever;

    public function attachToConnection(IConnection $connection): void
    {
        $this->dateStyleRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(),
            ConfigParam::DATE_STYLE,
            \Closure::fromCallable([DateStyle::class, 'fromString'])
        );
        $connName = $connection->getName();
        $this->localMeanTimeZoneRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(),
            ConfigParam::TIME_ZONE,
            function ($timeZone) use ($connName) {
                try {
                    $tz = new \DateTimeZone($timeZone);
                } catch (\Exception $e) {
                    $msg = "Time zone '$timeZone', as configured for the PostgreSQL connection $connName, is unknown "
                        . "to PHP. Falling back to UTC (only relevant for timestamptz values representing very old "
                        . "date/times).";
                    trigger_error($msg, E_USER_NOTICE);
                    return new \DateTimeZone('UTC');
                }

                $longitude = $tz->getLocation()['longitude'];
                $offset = $longitude * 24 / 360;
                $abs = abs($offset);
                $tzSpec = sprintf('%s%d:%02d', ($offset >= 0 ? '+' : '-'), (int)$abs, (int)(($abs - (int)$abs) * 60));
                // unfortunately, \DateTimeZone cannot be created with offsets precise to seconds
                return new \DateTimeZone($tzSpec);
            }
        );
    }

    public function detachFromConnection(): void
    {
        $this->dateStyleRetriever = null;
        $this->localMeanTimeZoneRetriever = null;
    }

    public function parseValue(string $extRepr)
    {
        if ($extRepr == 'infinity') {
            return TimestampTz::infinity();
        } elseif ($extRepr == '-infinity') {
            return TimestampTz::minusInfinity();
        }

        $dateStyle = $this->dateStyleRetriever->getValue();
        assert($dateStyle instanceof DateStyle);
        switch ($dateStyle->getFormat()) {
            default:
                trigger_error(
                    "Unexpected DateStyle format '{$dateStyle->getFormat()}', assuming the ISO style",
                    E_USER_WARNING
                );
            case DateStyle::FORMAT_ISO:
                preg_match('~^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+(?:\.\d+)?)([-+][^ ]+)( BC)?$~', $extRepr, $matches);
                list(, $y, $m, $d, $h, $i, $s, $z) = $matches;
                break;
            case DateStyle::FORMAT_GERMAN:
                preg_match('~^(\d+)\.(\d+)\.(\d+) (\d+):(\d+):(\d+(?:\.\d+)?) ([^ ]+)( BC)?$~', $extRepr, $matches);
                list(, $d, $m, $y, $h, $i, $s, $z) = $matches;
                break;
            case DateStyle::FORMAT_SQL:
                preg_match('~^(\d+)/(\d+)/(\d+) (\d+):(\d+):(\d+(?:\.\d+)?) ([^ ]+)( BC)?$~', $extRepr, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $m, $y, $h, $i, $s, $z) = $matches;
                } else {
                    list(, $m, $d, $y, $h, $i, $s, $z) = $matches;
                }
                break;
            case DateStyle::FORMAT_POSTGRES:
                preg_match('~^\w{3} (\d+|\w{3}) (\d+|\w{3}) (\d+):(\d+):(\d+(?:\.\d+)?) (\d+) ([^ ]+)( BC)?$~', $extRepr, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $ms, $h, $i, $s, $y, $z) = $matches;
                } else {
                    list(, $ms, $d, $h, $i, $s, $y, $z) = $matches;
                }
                static $monthNames = [
                    'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
                    'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
                ];
                $m = $monthNames[$ms];
                break;
        }

        if (isset($matches[8])) {
            $y = -$y;
        }
        if ($z == 'LMT') {
            $z = $this->localMeanTimeZoneRetriever->getValue();
        }
        return TimestampTz::fromParts((int)$y, (int)$m, (int)$d, (int)$h, (int)$i, $s, $z);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof TimestampTz) {
            $val = (is_numeric($val) ? TimestampTz::fromUnixTimestamp($val) : TimestampTz::fromISOString($val));
        }

        if ($val->isFinite()) {
            $tsStr = sprintf(
                "'%04d-%s%s%s%s'",
                abs($val->getYear()),
                $val->format('m-d H:i:s'),
                ($val->format('u') ? $val->format('.u') : ''),
                $val->getOffsetISOString(),
                ($val->getYear() < 0 ? ' BC' : '')
            );
            return $this->indicateType($strictType, $tsStr);
        } elseif ($val === TimestampTz::infinity()) {
            return $this->indicateType($strictType, "'infinity'");
        } elseif ($val === TimestampTz::minusInfinity()) {
            return $this->indicateType($strictType, "'-infinity'");
        } else {
            throw new \LogicException('A non-finite timezone-aware timestamp not recognized');
        }
    }
}
