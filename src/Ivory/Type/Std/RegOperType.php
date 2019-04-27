<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgOperatorNameRef;

/**
 * PostgreSQL object identifier type identifying an operator by its name.
 *
 * Represented as a {@link \Ivory\Value\PgOperatorNameRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegOperType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgOperatorNameRef::fromQualifiedName(...$this->parseObjectRef($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgOperatorNameRef) {
            return $this->serializeObjectRef($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
