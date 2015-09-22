<?php
namespace Ivory\Connection;

use Ivory\Command\IResult;
use Ivory\Exception\CommandException;
use Ivory\Exception\ConnectionException;

interface ICommandExecution // TODO: support prepared statements
{
    /**
     * Sends a raw SQL statement, as is, to the database, waits for its execution and returns the result.
     *
     * Just a single statement may be used. For sending multiple statements at once, use {@link rawMultiQuery()} or
     * {@link runScript()}.
     *
     * @param string $sqlStatement an SQL statement
     * @return IResult the result of the statement
     * @throws CommandException when the command is erroneous and PostgreSQL returns an error, or if
     *                            <tt>$sqlStatement</tt> actually contains multiple statements (e.g., separated by a
     *                            semicolon)
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     */
    function rawQuery($sqlStatement);

    /**
     * Sends one or more raw SQL statements to the database, waits for their execution and returns the results.
     *
     * The operation is equivalent to calling {@link rawQuery()} for each of the statements.
     *
     * @param string[]|\Traversable $sqlStatements
     *                                  list of strings, each containing one SQL statement;
     *                                  a traversable object of strings may alternatively be used, it is guaranteed to
     *                                    be iterated over only once;
     *                                  note it is NOT allowed to pass multiple statements in a single string (e.g.,
     *                                    separated by a semicolon) - pass them as individual list items, or use
     *                                    {@link runScript()} instead
     * @return IResult[] list of results, one for each statement;
     *                   if <tt>$sqlStatements</tt> is actually an associative array or a {@link \Traversable} object,
     *                     an associative array of results is returned, with each result stored under the same key and
     *                     in the same order as the corresponding statement from <tt>$sqlStatements</tt>
     * @throws CommandException when some of the statements are erroneous and PostgreSQL returns an error, or if there
     *                            are multiple statements in a single list item
     * @throws ConnectionException when an error occurred while sending the commands or processing the database response
     */
    function rawMultiQuery($sqlStatements);

    /**
     * Sends a script of one or more statements to the database, waits for their execution, and returns the results.
     *
     * Note a somewhat counter-intuitive semantics, induced by the semantics of <tt>pg_send_query()</tt>:
     * - an implicit <tt>BEGIN</tt> is issued before the script (if already in a transaction, it has no effect, though);
     * - an implicit <tt>BEGIN</tt> is issued after each explicit <tt>COMMIT</tt> or <tt>ROLLBACK</tt>;
     * - an implicit <tt>COMMIT</tt> is issued after the script unless there was an explicit <tt>BEGIN</tt> in this
     *   script with no following explicit <tt>COMMIT</tt> or <tt>ROLLBACK</tt>, and unless a transaction was already
     *   active before running this script and the script contains neither <tt>COMMIT</tt> nor <tt>ROLLBACK</tt>
     *
     * @param string $sqlScript a string containing one or more semicolon-separated statements
     * @return IResult[] list of results, one for each statement
     * @throws CommandException when some of the statements are erroneous and PostgreSQL returns an error
     * @throws ConnectionException when an error occurred while sending the commands or processing the database response
     */
    function runScript($sqlScript);
}
