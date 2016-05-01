<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\TxIdSnapshot;

/**
 * PostgreSQL transaction ID snapshot.
 *
 * Represented as a {@link \Ivory\Value\TxIdSnapshot} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/functions-info.html#FUNCTIONS-TXID-SNAPSHOT
 */
class TxIdSnapshotType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        else {
            return TxIdSnapshot::fromString($str);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TxIdSnapshot) {
            $val = TxIdSnapshot::fromString($val);
        }

        return "'" . $val->toString() . "'";
    }
}
