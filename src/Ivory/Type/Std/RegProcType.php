<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgProcedureNameRef;

/**
 * PostgreSQL object identifier type identifying a procedure by its name.
 *
 * Represented as a {@link \Ivory\Value\PgProcedureNameRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegProcType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgProcedureNameRef::fromQualifiedName(...$this->parseObjectRef($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgProcedureNameRef) {
            return $this->serializeObjectRef($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
