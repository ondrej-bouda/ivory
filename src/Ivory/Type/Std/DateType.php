<?php
namespace Ivory\Type\Std;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\Config\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\ConnectionDependentBaseType;
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
class DateType extends ConnectionDependentBaseType implements IDiscreteType
{
    use TotallyOrderedByPhpOperators;

    const MODE_ISO = 1;
    const MODE_SQL_DMY = 2;
    const MODE_SQL_MDY = 3;
    const MODE_GERMAN = 4;
    const MODE_PG_DMY = 5;
    const MODE_PG_MDY = 6;
    const MODE_PG_YMD = 7;


    /** @var ConnConfigValueRetriever */
    private $modeRetriever = null;

    public function attachToConnection(IConnection $connection)
    {
        $this->modeRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(),
            ConfigParam::DATE_STYLE,
            function ($dateStyleStr) {
                $dateStyle = DateStyle::fromString($dateStyleStr);
                switch ($dateStyle->getFormat()) {
                    case DateStyle::FORMAT_ISO:
                        return self::MODE_ISO;

                    case DateStyle::FORMAT_POSTGRES:
                        // The PostgreSQL manual says that "the POSTGRES style outputs date-only values in ISO format",
                        // but that's not quite true: unlike the ISO style, the POSTGRES style applies the d/m/y order.
                        switch ($dateStyle->getOrder()) {
                            case DateStyle::ORDER_DMY:
                                return self::MODE_PG_DMY;
                            case DateStyle::ORDER_MDY:
                                return self::MODE_PG_MDY;
                            case DateStyle::ORDER_YMD:
                                return self::MODE_PG_YMD;
                            default:
                                throw new \UnexpectedValueException('Unknown DateStyle order: ' . $dateStyle->getOrder());
                        }

                    case DateStyle::FORMAT_GERMAN:
                        return self::MODE_GERMAN;

                    case DateStyle::FORMAT_SQL:
                        switch ($dateStyle->getOrder()) {
                            case DateStyle::ORDER_DMY:
                                return self::MODE_SQL_DMY;
                            case DateStyle::ORDER_MDY:
                                return self::MODE_SQL_MDY;
                            default:
                                throw new \UnexpectedValueException('Unknown DateStyle order: ' . $dateStyle->getOrder());
                        }

                    default:
                        throw new \UnexpectedValueException('Unknown DateStyle format: ' . $dateStyle->getFormat());
                }
            }
        );
    }

    public function detachFromConnection()
    {
        $this->modeRetriever = null;
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

        $parts = explode(' BC', $str);

        switch ($this->modeRetriever->getValue()) {
            case self::MODE_ISO: // e.g., 1997-12-17
            case self::MODE_PG_YMD: // e.g., 1997-12-17
                if (!isset($parts[1])) {
                    return Date::fromISOString($str); // optimization of the major case
                }
                list($y, $m, $d) = explode('-', $parts[0]);
                break;

            case self::MODE_GERMAN: // e.g., 17.12.1997
                list($d, $m, $y) = explode('.', $parts[0]);
                break;

            case self::MODE_SQL_DMY: // e.g., 17/12/1997
                list($d, $m, $y) = explode('/', $parts[0]);
                break;

            case self::MODE_SQL_MDY: // e.g., 12/17/1997
                list($m, $d, $y) = explode('/', $parts[0]);
                break;

            case self::MODE_PG_DMY: // e.g., 17-12-1997
                list($d, $m, $y) = explode('-', $parts[0]);
                break;

            case self::MODE_PG_MDY: // e.g., 12-17-1997
                list($m, $d, $y) = explode('-', $parts[0]);
                break;

            default:
                throw new \UnexpectedValueException('Invalid parse mode: ' . $this->modeRetriever->getValue());
        }

        if (isset($parts[1])) {
            $y = -$y + 1; // year 2 BC is the ISO year -1
        }
        $isoStr = "$y-$m-$d";
        try {
            return Date::fromISOString($isoStr);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Invalid date: ' . $str, 0, $e);
        }
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
