<?php
namespace Ivory\Type\Std;

use Ivory\Connection\DateStyle;
use Ivory\Connection\IConnConfig;
use Ivory\Type\IDiscreteType;
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
 * - `ISO`, e.g., `1997-12-17`
 * - `SQL`, e.g., `12/17/1997`
 * - `Postgres`, e.g., `1997-12-17` (formatted the same as `ISO` for `date`-only values), or
 * - `German`, e.g., `17.12.1997`.
 *
 * Apart from that, the order of the day, month, and year in dates parsed from PostgreSQL by
 * {@link DateType::parseValue()} also depends on the `DateStyle` setting, but in rather limited fashion:
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
class DateType extends \Ivory\Type\BaseType implements IDiscreteType
{
	public function parseValue($str)
	{
		if ($str === null) {
			return null;
		}

		$matched = preg_match('~^(\d+)([-/.])(\d+)(?2)(\d+)(\s+BC)?$~', $str, $m);
		if (PHP_MAJOR_VERSION >= 7) {
			assert($matched, new \InvalidArgumentException('$str'));
		}
		else {
			assert($matched);
		}

		$p = [];
		list(, $p[0], $sep, $p[1], $p[2], $bc) = $m;
		$yearSgn = ($bc ? -1 : 1);

		if ($sep == '.') {
			list($day, $mon, $year) = $p; // German style, no need to look up the settings
		}
		else {
			/* TODO: Cache somehow! Serious performance penalty if performed for each and every date parsed from
			 *       PostgreSQL. Introduce a common caching mechanism, which would flush caches which depend on a
			 *       configuration setting upon detecting they were changed; or simply move the DateStyle-related code
			 *       to the IConnConfig, which would handle the problem.
			 *       Or, even, better, introduce specialized type classes pre-fabricated for the date style currently in
			 *       use, and switch it upon a date style change. Use the same mechanism for this type converter as well
			 *       as for any other - e.g., MoneyType. After all, some type converters might get optimized if they
			 *       could rely on a subset of permitted PostgreSQL syntax - the one actually used by PostgreSQL for
			 *       outputting the values, e.g., ArrayType.
			 */
			$dateStyleStr = $this->getConnection()->getConfig()->get(IConnConfig::OPT_DATE_STYLE);
			$dateStyle = DateStyle::fromString($dateStyleStr);
			switch ($dateStyle->getOrder()) {
				case DateStyle::ORDER_DMY:
					list($day, $mon, $year) = $p;
					break;

				case DateStyle::ORDER_MDY:
					list($mon, $day, $year) = $p;
					break;

				default:
					trigger_error(
						"Unexpected year/month/day order '{$dateStyle->getOrder()}', assuming year-month-day",
						E_USER_WARNING
					);
				case DateStyle::ORDER_YMD:
					list($year, $mon, $day) = $p;
			}
		}

		return Date::fromComponentsStrict($yearSgn * $year, $mon, $day);
	}

	public function serializeValue($val)
	{
		if ($val === null) {
			return 'NULL';
		}

		if (!$val instanceof Date) {
			$val = (is_numeric($val) ? Date::fromTimestamp($val) : Date::fromString($val));
		}

		return sprintf(
			"'%d-%d-%d%s'",
			abs($val->getYear()),
			$val->getMonth(),
			$val->getDay(),
			($val->getYear() < 0 ? ' BC' : '')
		);
	}

	public function compareValues($a, $b)
	{
		if ($a === null || $b === null) {
			return null;
		}

		// PHP 7: use the <=> operator as a shorthand
		if ($a < $b) {
			return -1;
		}
		elseif ($a == $b) {
			return 0;
		}
		else {
			return 1;
		}
	}

	public function step($delta, $value)
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
