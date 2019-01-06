<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\Config\ConnConfigValueRetriever;
use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnection;
use Ivory\Type\ConnectionDependentBaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Timestamp;

/**
 * Date and time, counted according to the Gregorian calendar, even in years before that calendar was introduced.
 *
 * Represented as a {@link \Ivory\Value\Timestamp} object.
 *
 * The values recognized by the {@link TimestampType::parseValue()} method are expected to be in one of the four styles
 * PostgreSQL may use for output, depending on the
 * {@link https://www.postgresql.org/docs/11/runtime-config-client.html#GUC-DATESTYLE DateStyle} environment
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
 * {@link https://www.postgresql.org/docs/11/datetime-input-rules.html} for details on PostgreSQL reading date
 * inputs).
 *
 * @see https://www.postgresql.org/docs/11/datatype-datetime.html
 * @see https://www.postgresql.org/docs/11/datetime-units-history.html
 * @see https://www.postgresql.org/docs/11/runtime-config-client.html#GUC-DATESTYLE
 */
class TimestampType extends ConnectionDependentBaseType implements ITotallyOrderedType
{
    /** @var ConnConfigValueRetriever */
    private $dateStyleRetriever;

    public function attachToConnection(IConnection $connection): void
    {
        $this->dateStyleRetriever = new ConnConfigValueRetriever(
            $connection->getConfig(),
            ConfigParam::DATE_STYLE,
            \Closure::fromCallable([DateStyle::class, 'fromString'])
        );
    }

    public function detachFromConnection(): void
    {
        $this->dateStyleRetriever = null;
    }

    public function parseValue(string $extRepr)
    {
        if ($extRepr == 'infinity') {
            return Timestamp::infinity();
        } elseif ($extRepr == '-infinity') {
            return Timestamp::minusInfinity();
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
                preg_match('~^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $extRepr, $matches);
                list(, $y, $m, $d, $h, $i, $s) = $matches;
                break;
            case DateStyle::FORMAT_GERMAN:
                preg_match('~^(\d+)\.(\d+)\.(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $extRepr, $matches);
                list(, $d, $m, $y, $h, $i, $s) = $matches;
                break;
            case DateStyle::FORMAT_SQL:
                preg_match('~^(\d+)/(\d+)/(\d+) (\d+):(\d+):(\d+(?:\.\d+)?).*?(BC)?$~', $extRepr, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $m, $y, $h, $i, $s) = $matches;
                } else {
                    list(, $m, $d, $y, $h, $i, $s) = $matches;
                }
                break;
            case DateStyle::FORMAT_POSTGRES:
                preg_match('~^\w{3} (\d+|\w{3}) (\d+|\w{3}) (\d+):(\d+):(\d+(?:\.\d+)?) (\d+).*?(BC)?$~', $extRepr, $matches);
                if ($dateStyle->getOrder() == DateStyle::ORDER_DMY) {
                    list(, $d, $ms, $h, $i, $s, $y) = $matches;
                } else {
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
        return Timestamp::fromParts((int)$y, (int)$m, (int)$d, (int)$h, (int)$i, $s);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Timestamp) {
            $val = (is_numeric($val) ? Timestamp::fromUnixTimestamp($val) : Timestamp::fromISOString($val));
        }

        if ($val->isFinite()) {
            return sprintf(
                "'%04d-%s%s%s'",
                abs($val->getYear()),
                $val->format('m-d H:i:s'),
                ($val->format('u') ? $val->format('.u') : ''),
                ($val->getYear() < 0 ? ' BC' : '')
            );
        } elseif ($val === Timestamp::infinity()) {
            return "'infinity'";
        } elseif ($val === Timestamp::minusInfinity()) {
            return "'-infinity'";
        } else {
            throw new \LogicException('A non-finite timestamp not recognized');
        }
    }
}
