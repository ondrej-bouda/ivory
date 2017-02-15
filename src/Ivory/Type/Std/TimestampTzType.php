<?php
namespace Ivory\Type\Std;

use Ivory\Connection\ConfigParam;
use Ivory\Connection\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\TimestampTz;

/**
 * Timezone-aware date and time, counted according to the Gregorian calendar, even in years before that calendar was
 * introduced.
 *
 * Represented as a {@link \Ivory\Value\TimestampTz} object.
 *
 * The format of recognized values is the same as for {@link \Ivory\Value\Timestamp}. All possible
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE DateStyle} settings are
 * supported.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE
 */
class TimestampTzType extends BaseType implements ITotallyOrderedType
{
    private $dateStyleRetriever;
    private $localMeanTimeZoneRetriever;

    public function __construct(string $schemaName, string $name, IConnection $connection)
    {
        parent::__construct($schemaName, $name, $connection);

        $this->dateStyleRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(), ConfigParam::DATE_STYLE, [DateStyle::class, 'fromString']
        );
        $this->localMeanTimeZoneRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(),
            ConfigParam::TIME_ZONE,
            function ($timeZone) use ($connection) {
                try {
                    $tz = new \DateTimeZone($timeZone);
                    $longitude = $tz->getLocation()['longitude'];
                    $offset = $longitude * 24 / 360;
                    $abs = abs($offset);
                    $tzSpec = sprintf('%s%d:%02d', ($offset >= 0 ? '+' : '-'), (int)$abs, (int)(($abs - (int)$abs) * 60));
                    // unfortunately, \DateTimeZone cannot be created with offsets precise to seconds
                    return new \DateTimeZone($tzSpec);
                } catch (\Exception $e) {
                    $msg = "Time zone '$timeZone', as configured for the PostgreSQL connection {$connection->getName()}, "
                        . "is unknown to PHP. Falling back to UTC (only relevant for timestamptz values representing "
                        . "very old date/times).";
                    trigger_error($msg, E_USER_NOTICE);
                    return new \DateTimeZone('UTC');
                }
            }
        );
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } elseif ($str == 'infinity') {
            return TimestampTz::infinity();
        } elseif ($str == '-infinity') {
            return TimestampTz::minusInfinity();
        }

        /** @var DateStyle $dateStyle */
        $dateStyle = $this->dateStyleRetriever->getValue();
        switch ($dateStyle->getFormat()) {
            default:
                trigger_error(
                    "Unexpected DateStyle format '{$dateStyle->getFormat()}', assuming the ISO style",
                    E_USER_WARNING
                );
            case DateStyle::FORMAT_ISO:
                preg_match('~^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+(?:\.\d+)?)([-+][^ ]+)( BC)?$~', $str, $matches);
                list(, $y, $m, $d, $h, $i, $s, $z) = $matches;
                break;
            case DateStyle::FORMAT_GERMAN:
                preg_match('~^(\d+)\.(\d+)\.(\d+) (\d+):(\d+):(\d+(?:\.\d+)?) ([^ ]+)( BC)?$~', $str, $matches);
                list(, $d, $m, $y, $h, $i, $s, $z) = $matches;
                break;
            case DateStyle::FORMAT_SQL:
                preg_match('~^(\d+)/(\d+)/(\d+) (\d+):(\d+):(\d+(?:\.\d+)?) ([^ ]+)( BC)?$~', $str, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $m, $y, $h, $i, $s, $z) = $matches;
                } else {
                    list(, $m, $d, $y, $h, $i, $s, $z) = $matches;
                }
                break;
            case DateStyle::FORMAT_POSTGRES:
                preg_match('~^\w{3} (\d+|\w{3}) (\d+|\w{3}) (\d+):(\d+):(\d+(?:\.\d+)?) (\d+) ([^ ]+)( BC)?$~', $str, $matches);
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
        return TimestampTz::fromParts($y, $m, $d, $h, $i, $s, $z);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TimestampTz) {
            $val = (is_numeric($val) ? TimestampTz::fromUnixTimestamp($val) : TimestampTz::fromISOString($val));
        }

        if ($val->isFinite()) {
            return sprintf(
                "'%04d-%s%s%s%s'",
                abs($val->getYear()),
                $val->format('m-d H:i:s'),
                ($val->format('u') ? $val->format('.u') : ''),
                $val->getOffsetISOString(),
                ($val->getYear() < 0 ? ' BC' : '')
            );
        } elseif ($val === TimestampTz::infinity()) {
            return "'infinity'";
        } elseif ($val === TimestampTz::minusInfinity()) {
            return "'-infinity'";
        } else {
            throw new \LogicException('A non-finite timezone-aware timestamp not recognized');
        }
    }


    public function compareValues($a, $b)
    {
        if ($a === null || $b === null || !$a instanceof TimestampTz || !$b instanceof TimestampTz) {
            return null;
        }

        return ($a->toUnixTimestamp() - $b->toUnixTimestamp());
    }
}
