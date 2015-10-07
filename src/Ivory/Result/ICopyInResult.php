<?php
namespace Ivory\Result;

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
     * @param string $line line of data; may or may not be terminated with a newline character
     */
    function putLine($line);

    /**
     * Finalizes copying data to the database.
     *
     * The `\.` sequence (backslash and a dot) is automatically sent to the database server by this method and
     * {@link pg_end_copy()} function is called then.
     */
    function end();
}
