<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference by name to a stored PostgreSQL operator, i.e., database object stored in `pg_catalog.pg_oper`.
 *
 * This is just a reference by name, i.e., the referenced operator may not be overloaded. For referencing overloaded
 * operators, {@link PgOperatorRef} must be used.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgOperatorNameRef extends PgObjectRefBase
{
}
