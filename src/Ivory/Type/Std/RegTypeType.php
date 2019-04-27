<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\PgTypeRef;

/**
 * PostgreSQL object identifier type identifying a data type by its name.
 *
 * Represented as a {@link \Ivory\Value\PgTypeRef} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class RegTypeType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        return PgTypeRef::fromQualifiedName(...$this->parseObjectRef($extRepr));
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof PgTypeRef) {
            return $this->serializeObjectRef($val, $strictType);
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
