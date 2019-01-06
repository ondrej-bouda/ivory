<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\TxIdSnapshot;

/**
 * PostgreSQL transaction ID snapshot.
 *
 * Represented as a {@link \Ivory\Value\TxIdSnapshot} object.
 *
 * @see https://www.postgresql.org/docs/11/functions-info.html#FUNCTIONS-TXID-SNAPSHOT
 */
class TxIdSnapshotType extends BaseType
{
    public function parseValue(string $extRepr)
    {
        return TxIdSnapshot::fromString($extRepr);
    }

    public function serializeValue($val): string
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
