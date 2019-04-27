<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a PostgreSQL data type, i.e., database object stored in `pg_catalog.pg_type`.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgTypeRef extends PgObjectRefBase
{
}
