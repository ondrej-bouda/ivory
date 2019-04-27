<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a stored PostgreSQL operator, i.e., database object stored in `pg_catalog.pg_oper`.
 *
 * The reference specifies both the operator name and types of its arguments. For referring using only the operator
 * name, use {@link PgOperatorNameRef} (that can only be used for non-overloaded operators, though).
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgOperatorRef extends PgObjectSignatureRefBase
{
}
