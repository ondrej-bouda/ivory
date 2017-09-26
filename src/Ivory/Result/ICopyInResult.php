<?php
namespace Ivory\Result;

use Ivory\Exception\ConnectionException;

/**
 * Result of a successful `COPY FROM STDIN` statement, i.e., data directly pushed to a database table.
 *
 * Note that until the {@link ICopyInResult::end()} method is called on this result, the connection is blocked.
 */
interface ICopyInResult extends IResult
{
    /**
     * Puts a line of data to the database.
     *
     * @param string $line line of data; a complete line must be terminated with the newline character
     * @throws ConnectionException if the connection got closed or broken
     */
    function putLine(string $line): void;

    /**
     * Finalizes copying data to the database.
     *
     * The `\.` sequence (backslash and a dot) is automatically sent to the database server by this method and the
     * {@link pg_end_copy()} function is called then.
     *
     * @throws ConnectionException if the connection got closed or broken
     */
    function end(): void;
}
