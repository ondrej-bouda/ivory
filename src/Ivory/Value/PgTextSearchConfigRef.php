<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a stored PostgreSQL text search config, i.e., database object stored in `pg_catalog.pg_ts_config`.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgTextSearchConfigRef extends PgObjectRefBase
{
}
