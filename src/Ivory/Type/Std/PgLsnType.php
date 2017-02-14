<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\PgLogSequenceNumber;

/**
 * PostgreSQL log sequence number.
 *
 * Represented as a {@link \Ivory\Value\PgLogSequenceNumber} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-pg-lsn.html
 */
class PgLsnType extends BaseType implements ITotallyOrderedType
{
    use TotallyOrderedByPhpOperators;

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return PgLogSequenceNumber::fromString($str);
        }
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
