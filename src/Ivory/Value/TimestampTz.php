<?php
namespace Ivory\Value;

/**
 * Timezone-aware representation of date and time according to the
 * {@link https://en.wikipedia.org/wiki/Proleptic_Gregorian_calendar proleptic} Gregorian calendar.
 *
 * For a timezone-unaware date/time, see {@link Timestamp}.
 *
 * As in PostgreSQL, there are two special date/time values, `-infinity` and `infinity`, representing a date/time
 * respectively before or after any other date/time. There are special factory methods
 * {@link TimestampTz::minusInfinity()} and {@link TimestampTz::infinity()} for getting these values.
 *
 * All the operations work correctly beyond the UNIX timestamp range bounded by 32bit integers, i.e., it is no problem
 * calculating with year 12345, for example.
 *
 * Note the date/time value is immutable, i.e., once constructed, its value cannot be changed.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datetime-units-history.html
 */
class TimestampTz extends Timestamp
{

}
