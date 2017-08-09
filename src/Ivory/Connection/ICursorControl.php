<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Query\IRelationDefinition;
use Ivory\Relation\ICursor;

interface ICursorControl
{
    /**
     * Declares a new cursor in the current session.
     *
     * Note that, unless declared with {@link ICursor::HOLDABLE}, a cursor gets automatically closed by PostgreSQL by
     * the end of the current transaction. Hence, it is desirable to declare non-holdable cursors while a transaction is
     * open, and stop using it once the transaction gets committed or rolled back.
     *
     * To ensure the cursor will be scrollable, option {@link ICursor::SCROLLABLE} may be used. Conversely, to forbid
     * scrolling, option {@link ICursor::NON_SCROLLABLE} is to be used. If neither of these options is provided,
     * PostgreSQL will decide according to the
     * {@see https://www.postgresql.org/docs/11/sql-declare.html `DECLARE` specification}.
     *
     * The cursor may be declared as binary with option {@link ICursor::BINARY}. Ivory will not be able to fetch from
     * it, though, only manipulate it and pass it around.
     *
     * @param string $name name for the cursor
     * @param IRelationDefinition $relationDefinition e.g., result of {@link SqlRelationDefinition::fromPattern()}
     * @param int $options bitmask of options
     * @return ICursor handle for the declared cursor
     */
    function declareCursor(string $name, IRelationDefinition $relationDefinition, int $options = 0): ICursor;

    /**
     * Retrieves all cursors which are currently available.
     *
     * @return ICursor[] map: cursor name => cursor; sorted by the time of cursor declaration
     */
    function getAllCursors(): array;

    /**
     * Closes all open cursors.
     */
    function closeAllCursors(): void;
}
