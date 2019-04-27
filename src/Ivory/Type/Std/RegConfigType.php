<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgTextSearchConfigRef;

/**
 * PostgreSQL object identifier type identifying a text search configuration by its name.
 *
 * Represented as a {@link \Ivory\Value\PgTextSearchConfigRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegConfigType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgTextSearchConfigRef::fromQualifiedName(...$this->parseObjectRef($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgTextSearchConfigRef) {
            return $this->serializeObjectRef($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
