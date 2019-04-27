<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgOperatorRef;

/**
 * PostgreSQL object identifier type identifying an operator by its signature.
 *
 * Represented as a {@link \Ivory\Value\PgOperatorRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegOperatorType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgOperatorRef::fromQualifiedName(...$this->parseObjectSignature($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgOperatorRef) {
            return $this->serializeObjectSignature($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
