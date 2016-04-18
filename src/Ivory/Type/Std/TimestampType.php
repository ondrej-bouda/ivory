<?php
namespace Ivory\Type\Std;

use Ivory\Connection\ConfigParam;
use Ivory\Connection\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\DateTime;

/**
 * Date and time, counted according to the Gregorian calendar, even in years before that calendar was introduced.
 *
 * Represented as a {@link \Ivory\Value\DateTime} object.
 *
 * The values recognized by the {@link TimestampType::parseValue()} method are expected to be in one of the four styles
 * PostgreSQL may use for output, depending on the
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE DateStyle} environment
 * setting:
 * - `ISO`, e.g., `1997-12-17 07:37:16.1234` (the letter `T` might alternatively be used instead of the space),
 * - `SQL`, e.g., `12/17/1997 07:37:16.1234`,
 * - `Postgres`, e.g., `Wed Dec 17 07:37:16.1234 1997`, or
 * - `German`, e.g., `17.12.1997 07:37:16.1234`.
 *
 * Apart from that, the order of the day, month, and year in dates parsed from PostgreSQL by
 * {@link TimestampType::parseValue()} also depends on the `DateStyle` setting, but in a rather limited fashion:
 * - `SQL` and `Postgres` both expect either `MDY` or `DMY` (and default to `MDY` for other values),
 * - `ISO` and `German` are insensitive of the month-day-year order.
 *
 * As for serializing values to PostgreSQL by the {@link TimestampType::serializeValue()} method, the `DateStyle`
 * setting is irrelevant - the values are serialized in `DateStyle`-agnostic way (see
 * {@link http://www.postgresql.org/docs/9.4/static/datetime-input-rules.html} for details on PostgreSQL reading date
 * inputs).
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE
 */
class TimestampType extends BaseType implements ITotallyOrderedType
{
    use TotallyOrderedByPhpOperators;


    private $dateStyleRetriever;

    public function __construct($schemaName, $name, IConnection $connection)
    {
        parent::__construct($schemaName, $name, $connection);

        $this->dateStyleRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(), ConfigParam::DATE_STYLE, [DateStyle::class, 'fromString']
        );
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        elseif ($str == 'infinity') {
            return DateTime::infinity();
        }
        elseif ($str == '-infinity') {
            return DateTime::minusInfinity();
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
                preg_match('~^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $str, $matches);
                list(, $y, $m, $d, $h, $i, $s) = $matches;
                break;
            case DateStyle::FORMAT_GERMAN:
                preg_match('~^(\d+)\.(\d+)\.(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $str, $matches);
                list(, $d, $m, $y, $h, $i, $s) = $matches;
                break;
            case DateStyle::FORMAT_SQL:
                preg_match('~^(\d+)/(\d+)/(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $str, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $m, $y, $h, $i, $s) = $matches;
                }
                else {
                    list(, $m, $d, $y, $h, $i, $s) = $matches;
                }
                break;
            case DateStyle::FORMAT_POSTGRES:
                preg_match('~^\w{3} (\d+|\w{3}) (\d+|\w{3}) (\d+):(\d+):(\d+(?:\.\d+)?) (\d+).*?(BC)?$~', $str, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $ms, $h, $i, $s, $y) = $matches;
                }
                else {
                    list(, $ms, $d, $h, $i, $s, $y) = $matches;
                }
                static $monthNames = [
                    'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
                    'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
                ];
                $m = $monthNames[$ms];
                break;
        }

        if (isset($matches[7])) {
            $y = -$y;
        }
        return DateTime::fromParts($y, $m, $d, $h, $i, $s);
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof DateTime) {
            $val = (is_numeric($val) ? DateTime::fromTimestamp($val) : DateTime::fromISOString($val));
        }

        if ($val->isFinite()) {
            return sprintf(
                "'%04d-%02d-%02d %02d:%02d:%02d%s%s'",
                abs($val->getYear()),
                $val->getMonth(),
                $val->getDay(),
                $val->getHour(),
                $val->getMinute(),
                $val->getSecond(),
                ($val->format('u') ? $val->format('.u') : ''),
                ($val->getYear() < 0 ? ' BC' : '')
            );
        }
        elseif ($val === DateTime::infinity()) {
            return "'infinity'";
        }
        elseif ($val === DateTime::minusInfinity()) {
            return "'-infinity'";
        }
        else {
            throw new \LogicException('A non-finite date not recognized');
        }
    }
}