<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Connection\IConnection;
use Ivory\Lang\Sql\Types;
use Ivory\Relation\Cursor;
use Ivory\Relation\ICursor;
use Ivory\Type\ConnectionDependentBaseType;

/**
 * PostgreSQL reference to a cursor.
 *
 * Represented as an {@link \Ivory\Relation\ICursor} object.
 *
 * @see https://www.postgresql.org/docs/11/plpgsql-cursors.html
 */
class RefCursorType extends ConnectionDependentBaseType
{
    private $connection;

    public function attachToConnection(IConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function detachFromConnection(): void
    {
        $this->connection = null;
    }

    public function parseValue(string $extRepr): ICursor
    {
        return new Cursor($this->connection, $extRepr);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val instanceof ICursor) {
            $cursorName = $val->getName();
        } else {
            $cursorName = $val;
        }

        return $this->indicateType($strictType, Types::serializeString($cursorName));
    }
}
