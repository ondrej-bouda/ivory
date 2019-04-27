<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgProcedureRef;

/**
 * PostgreSQL object identifier type identifying a procedure by its signature.
 *
 * Represented as a {@link \Ivory\Value\PgProcedureRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegProcedureType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgProcedureRef::fromQualifiedName(...$this->parseObjectSignature($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgProcedureRef) {
            return $this->serializeObjectSignature($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
