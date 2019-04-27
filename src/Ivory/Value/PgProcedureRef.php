<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a stored PostgreSQL procedure, i.e., database object stored in `pg_catalog.pg_proc`.
 *
 * The reference specifies both the procedure name and types of its arguments. For referring using only the procedure
 * name, use {@link PgProcedureNameRef} (that can only be used for non-overloaded procedures, though).
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgProcedureRef extends PgObjectSignatureRefBase
{
}
