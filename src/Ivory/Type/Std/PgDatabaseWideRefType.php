<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;

/**
 * PostgreSQL object identifier type identifying a database object above any namespace.
 *
 * Represented as a PHP `string`.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class PgDatabaseWideRefType extends PgObjectRefTypeBase
{
    public function parseValue(string $extRepr)
    {
        [$nspName, $objectName] = $this->parseObjectRef($extRepr);
        if ($nspName === null) {
            return $objectName;
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } else {
            return $this->indicateType($strictType, Types::serializeString(Types::serializeIdent($val)));
        }
    }
}
