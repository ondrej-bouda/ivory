<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference by name to a stored PostgreSQL procedure, i.e., database object stored in `pg_catalog.pg_proc`.
 *
 * This is just a reference by name, i.e., the referenced procedure may not be overloaded. For referencing overloaded
 * procedures, {@link PgProcedureRef} must be used.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgProcedureNameRef extends PgObjectRefBase
{
}
