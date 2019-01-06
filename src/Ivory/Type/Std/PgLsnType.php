<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\PgLogSequenceNumber;

/**
 * PostgreSQL log sequence number.
 *
 * Represented as a {@link \Ivory\Value\PgLogSequenceNumber} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-pg-lsn.html
 */
class PgLsnType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        return PgLogSequenceNumber::fromString($extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof PgLogSequenceNumber) {
            $val = PgLogSequenceNumber::fromString($val);
        }

        return "'" . $val->toString() . "'";
    }
}
