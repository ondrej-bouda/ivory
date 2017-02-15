<?php
namespace Ivory\Type\Std;

use Ivory\Connection\ConfigParam;
use Ivory\Connection\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\BaseType;
use Ivory\Type\IDiscreteType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\Date;

/**
 * Date, counted according to the Gregorian calendar, even in years before that calendar was introduced.
 *
 * Represented as a {@link \Ivory\Value\Date} object.
 *
 * The values recognized by the {@link DateType::parseValue()} method are expected to be in one of the four styles
 * PostgreSQL may use for output, depending on the
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE DateStyle} environment
 * setting:
 * - `ISO`, e.g., `1997-12-17`,
 * - `SQL`, e.g., `12/17/1997`,
 * - `Postgres`, e.g., `12-17-1997`, or
 * - `German`, e.g., `17.12.1997`.
 *
 * Apart from that, the order of the day, month, and year in dates parsed from PostgreSQL by
 * {@link DateType::parseValue()} also depends on the `DateStyle` setting, but in a rather limited fashion:
 * - `SQL` and `Postgres` both expect either `MDY` or `DMY` (and default to `MDY` for other values),
 * - `ISO` and `German` are insensitive of the month-day-year order.
 *
 * As for serializing values to PostgreSQL by the {@link DateType::serializeValue()} method, the `DateStyle` setting is
 * irrelevant - the values are serialized in `DateStyle`-agnostic way (see
 * {@link http://www.postgresql.org/docs/9.4/static/datetime-input-rules.html} for details on PostgreSQL reading date
 * inputs).
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 * @see http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE
 */
class DateType extends BaseType implements IDiscreteType
{
    use TotallyOrderedByPhpOperators;


    private $dateStyleRetriever;

    public function __construct(string $schemaName, string $name, IConnection $connection)
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
        } elseif ($str == 'infinity') {
            return Date::infinity();
        } elseif ($str == '-infinity') {
            return Date::minusInfinity();
        }

        $matched = preg_match('~^(\d+)([-/.])(\d+)(?2)(\d+)(\s+BC)?$~', $str, $m);
        if (PHP_MAJOR_VERSION >= 7) {
            assert($matched, new \InvalidArgumentException('$str'));
        } else {
            assert($matched);
        }

        $p = [];
        list(, $p[0], $sep, $p[1], $p[2]) = $m;
        $yearSgn = (isset($m[5]) ? -1 : 1);

        if ($sep == '.') {
            list($day, $mon, $year) = $p; // German style, no need to look up the settings
        } else {
            /** @var DateStyle $dateStyle */
            $dateStyle = $this->dateStyleRetriever->getValue();
            $partsOrder = $dateStyle->getOrder();
            switch ($partsOrder) {
                case DateStyle::ORDER_DMY:
                    list($day, $mon, $year) = $p;
                    break;

                case DateStyle::ORDER_MDY:
                    list($mon, $day, $year) = $p;
                    break;

                default:
                    trigger_error(
                        "Unexpected year/month/day order '{$partsOrder}', assuming year-month-day",
                        E_USER_WARNING
                    );
                case DateStyle::ORDER_YMD:
                    list($year, $mon, $day) = $p;
            }
        }

        return Date::fromPartsStrict($yearSgn * $year, $mon, $day);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Date) {
            $val = (is_numeric($val) ? Date::fromUnixTimestamp($val) : Date::fromISOString($val));
        }

        if ($val->isFinite()) {
            return sprintf(
                "'%04d-%02d-%02d%s'",
                abs($val->getYear()),
                $val->getMonth(),
                $val->getDay(),
                ($val->getYear() < 0 ? ' BC' : '')
            );
        } elseif ($val === Date::infinity()) {
            return "'infinity'";
        } elseif ($val === Date::minusInfinity()) {
            return "'-infinity'";
        } else {
            throw new \LogicException('A non-finite date not recognized');
        }
    }

    public function step(int $delta, $value)
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Date) {
            throw new \InvalidArgumentException('$value');
        }

        return $value->addDay($delta);
    }
}
