<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\ClosedCursorException;

/**
 * Handle for controlling a cursor.
 *
 * A cursor points to a position within a relation (table data) and allows fetching its tuples.
 *
 * Like in PostgreSQL, the initial position is before the first tuple. A fetch operation consists of moving the cursor
 * to the desired position (e.g., the next tuple) and then retrieving the tuple (or even multiple tuples) at that
 * position. When there are no more tuples, the cursor ends pointing after the last tuple.
 *
 * Unless the cursor is _scrollable_, one can only use moving to the previous or to the next tuple, and fetch just a
 * single tuple at once. If the cursor is scrollable, however, it is possible to move the cursor to a new absolute or
 * relative position and read one or more tuples in a single call. Deciding whether a given cursor is scrollable is up
 * to the user. Especially, Ivory will allow calling any move methods (and PostgreSQL will eventually raise an exception
 * in case the move is not possible). To be sure, one can declare the cursor as scrollable, or use
 * {@link ICursor::isScrollable()} to find out, although Ivory might need to query the database if it does not know the
 * cursor scrollability yet.
 *
 * Note that unless declared as _holdable_, the cursor is closed by PostgreSQL at the end of transaction. Alternatively,
 * a cursor may be closed explicitly using the {@link ICursor::close()} method. In either case, any operations on a
 * closed cursor will raise an {@link \Ivory\Exception\ClosedCursorException} or an
 * {@link \Ivory\Exception\StatementException}. Method {@link ICursor::isHoldable()} may be used to find out whether the
 * cursor is closed.
 *
 * @see https://www.postgresql.org/docs/11/plpgsql-cursors.html
 * @see https://www.postgresql.org/docs/11/sql-declare.html
 * @see https://www.postgresql.org/docs/11/sql-fetch.html
 * @see https://www.postgresql.org/docs/11/sql-move.html
 * @see https://www.postgresql.org/docs/11/sql-close.html
 * @see https://www.postgresql.org/docs/11/view-pg-cursors.html
 */
interface ICursor extends \IteratorAggregate
{
    const BINARY = 0b0001;
    const HOLDABLE = 0b0010;
    const SCROLLABLE = 0b0100;
    const NON_SCROLLABLE = 0b1000;

    const ALL_REMAINING = PHP_INT_MAX;
    const ALL_FOREGOING = PHP_INT_MIN;

    /**
     * Returns the name of the cursor.
     */
    function getName(): string;

    /**
     * Returns characteristics of the cursor.
     */
    function getProperties(): CursorProperties;

    /**
     * Moves the cursor to a new position relative to the current one, and fetches the row.
     *
     * If `$moveBy` is:
     * - positive, the cursor is moved `$moveBy` rows ahead;
     * - negative, the cursor is moved `-$moveBy` rows backwards;
     * - zero, the cursor stays at its current position.
     *
     * To just move the cursor without actually needing the row, {@link moveBy()} will be more efficient.
     *
     * Note that passing negative or zero `$moveBy` requires the cursor to be scrollable (otherwise, PostgreSQL raises
     * an exception, i.e., a `StatementException` is thrown).
     *
     * @param int $moveBy
     * @return ITuple|null tuple located at the new position, or <tt>null</tt> if before the first or after the last row
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function fetch(int $moveBy = 1): ?ITuple;

    /**
     * Moves the cursor to a new absolute position and fetches the row.
     *
     * If `$position` is:
     * - positive, the cursor is moved to `$position`'th row from the start (1 for the first row);
     * - negative, the cursor is moved to `-$position`'th row from the end (-1 for the last row);
     * - zero, the cursor is moved before the first row (and thus `null` is returned).
     *
     * If out of range, the cursor gets moved after the last row (`$position > 0`) or before the first row
     * (`$position < 0`).
     *
     * To just move the cursor without actually needing the row, {@link moveTo()} will be more efficient.
     *
     * Note that non-forward-only fetching requires the cursor to be scrollable (otherwise, PostgreSQL raises an
     * exception, i.e., a `StatementException` is thrown).
     *
     * @param int $position
     * @return ITuple|null tuple located at the new position, or <tt>null</tt> if before the first or after the last row
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function fetchAt(int $position): ?ITuple;

    /**
     * Fetches a given number of rows from the cursor.
     *
     * If `$count` is:
     * - positive, the next `$count` tuples are fetched;
     * - negative, the previous `-$count` tuples are fetched (i.e., taking `-$count` tuples moving backwards);
     * - zero, the current tuple is re-fetched;
     * - {@link ICursor::ALL_REMAINING}, all remaining tuples are fetched;
     * - {@link ICursor::ALL_FOREGOING}, all prior tuples are fetched (i.e., take all tuples while moving backwards).
     *
     * To just move the cursor without actually needing the rows, {@link moveAndCount()} will be more efficient.
     *
     * Note that passing negative or zero `$count` requires the cursor to be scrollable (otherwise, PostgreSQL raises an
     * exception, i.e., a `StatementException` is thrown).
     *
     * @param int $count
     * @return ITuple[]
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function fetchMulti(int $count): array;


    /**
     * Moves the cursor to the specified position relative to the current one.
     *
     * If `$offset` is:
     * - positive, the cursor is moved `$offset` rows ahead;
     * - negative, the cursor is moved `-$offset` rows backwards;
     * - zero, the cursor stays at its current position (i.e., this is a no-op).
     *
     * This is similar to {@link fetch()} except that no rows are returned.
     *
     * Note that passing negative `$offset` requires the cursor to be scrollable (otherwise, PostgreSQL raises an
     * exception, i.e., a `StatementException` is thrown).
     *
     * @param int $offset
     * @return int 1 if the cursor is positioned to a valid row, 0 if before the first or after the last row
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function moveBy(int $offset): int;

    /**
     * Moves the cursor to the specified absolute position.
     *
     * If `$position` is:
     * - positive, the cursor is moved to `$position`'th row from the start (1 for the first row);
     * - negative, the cursor is moved to `-$position`'th row from the end (-1 for the last row);
     * - zero, the cursor is moved before the first row.
     *
     * If out of range, the cursor gets moved after the last row (`$position > 0`) or before the first row
     * (`$position < 0`).
     *
     * This is similar to {@link fetchAt()} except that no rows are returned.
     *
     * Note that using this method requires the cursor to be scrollable (otherwise, PostgreSQL raises an exception,
     * i.e., a `StatementException` is thrown).
     *
     * @param int $position
     * @return int 1 if the cursor is positioned to a valid row, 0 if before the first or after the last row
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function moveTo(int $position): int;

    /**
     * Moves the cursor relatively to the current position, and tells the number of rows passed through.
     *
     * If `$offset` is:
     * - positive, the cursor is moved `$offset` rows ahead;
     * - negative, the cursor is moved `-$offset` rows backwards;
     * - zero, the cursor stays at the current position (`1` is returned if the cursor is at a valid row, `0` if not);
     * - {@link ICursor::ALL_REMAINING}, all remaining tuples are traversed;
     * - {@link ICursor::ALL_FOREGOING}, all prior tuples are traversed (i.e., pass all tuples while moving backwards).
     *
     * This is similar to {@link fetchMulti()} except that the rows are just counted, not returned.
     *
     * Note that passing negative `$count` requires the cursor to be scrollable (otherwise, PostgreSQL raises an
     * exception, i.e., a `StatementException` is thrown).
     *
     * @param int $offset
     * @return int the number of valid rows passed through during the cursor movement (at most <tt>abs($offset)</tt>)
     * @throws ClosedCursorException if the cursor is known to be closed
     */
    function moveAndCount(int $offset): int;

    /**
     * Closes the cursor so that PostgreSQL may free resources associated with it.
     *
     * After being closed, moving or fetching from it is an error. Thus, it is best to dispose this `ICursor` object.
     *
     * @throws ClosedCursorException if the cursor is known to already be closed
     */
    function close(): void;

    /**
     * Tells whether the cursor is already closed.
     */
    function isClosed(): bool;

    /**
     * {@inheritDoc}
     *
     * If `$bufferSize` is given as a positive integer, a buffer of the given size will be used for fetching tuples from
     * the cursor instead of fetching each row separately.
     */
    function getIterator(int $bufferSize = 0): \Traversable;
}
