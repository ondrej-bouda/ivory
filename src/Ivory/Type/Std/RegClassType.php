<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgRelationRef;

/**
 * PostgreSQL object identifier type identifying a relation by its name.
 *
 * Represented as a {@link \Ivory\Value\PgRelationRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegClassType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgRelationRef::fromQualifiedName(...$this->parseObjectRef($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgRelationRef) {
            return $this->serializeObjectRef($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
