<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Query\ICommandRecipe;
use Ivory\Query\IRelationRecipe;
use Ivory\Result\ICommandResult;
use Ivory\Result\IQueryResult;
use Ivory\Result\IResult;
use Ivory\Exception\StatementException;
use Ivory\Exception\ConnectionException;

// TODO: support prepared statements
/**
 * Execution of statements on the connected database.
 *
 * For type safety and transparency reasons, Ivory strictly distinguishes the executed statements to
 * - *queries*, which result in a relation, and
 * - *commands*, the result of which is just a summary information, such as the number of affected rows in a table.
 * Using the {@link IStatementExecution::query()} method with an SQL statement not returning data set (such as a plain
 * `INSERT` without the `RETURNING` clause) is an error, as well as calling {@link IStatementExecution::command()} with
 * an SQL statement returning data (such as `SELECT`). For convenience, mixing the two will not result in an exception,
 * though, so that the already executed statement is not compromised. Instead, a mere warning is issued and a substitute
 * result object is returned (an empty relation for a command executed by {@link IStatementExecution::query()}, and a
 * result reporting the number of returned rows in case of query executed by {@link IStatementExecution::command()}).
 *
 * @internal Ivory design note: Traditionally, in other database layers (including the pgsql extension), methods like
 * query() serve both for reading data from the database and for writing data to it, requiring the user to use the
 * results of such a query() method in the right way. The approach applied in Ivory is not to confuse the terms by
 * defining a *query()* method documented as "Executes an SQL statement", but rather to enhance type safety and promote
 * code clarity. Thus the separation in two distinct methods, {@link IStatementExecution::query()} and
 * {@link IStatementExecution::command()}. After all, processing the result of either of them is fairly different from
 * the result of the other one. And even if one changes a plain <tt>INSERT</tt> to <tt>INSERT ... RETURNING</tt>, the
 * change from <tt>query()</tt> to <tt>command()</tt> will be the tiniest adjustment of the related source code.
 */
interface IStatementExecution
{
    /**
     * Queries the database for a relation using an SQL pattern, waits for its execution and returns the resulting
     * relation.
     *
     * This is an overloaded method. There are three variants:
     * 1. The first argument is either an SQL pattern string or already parsed {@link SqlPattern} object. Values for all
     *    its positional parameters must follow. Optionally, any number of further SQL patterns (again, as strings or
     *    {@link SqlPattern}s) may be given then, each immediately followed by values for its positional parameters. All
     *    such SQL patterns are combined together as fragments. If any of the fragments has a named parameter, the map
     *    of values for named parameters must be given as the very last argument.
     * 2. The first argument is an {@link SqlRecipe} object. No positional arguments are expected in this case, only the
     *    optional map of values for named parameters. As the `SqlRecipe` might have already set all its named
     *    parameters, even this argument might not be necessary.
     * 3. The first and the only argument is an {@link IRelationRecipe} object.
     *
     * Examples:
     * - `query('SELECT 42')`
     * - `query('SELECT %int', 42)`
     * - `query('SELECT * FROM %ident', 'tbl', 'WHERE a = %int AND b = %s', 42, 'wheee')`
     * - `query('SELECT * FROM %ident WHERE a = %int:a AND b = %s:b', 't', ['a' => 42, 'b' => 'wheee'])`
     *
     * See {@link SqlPattern} documentation for thorough details on SQL patterns.
     *
     * Note the strict distinction of *queries* and *commands* - see the {@link IStatementExecution} docs. If an SQL
     * command is executed using this method, a relation of no columns and no rows is returned and a warning is issued.
     *
     * If executing the query immediately is not appropriate, consider using {@link dataSource()} instead.
     *
     * @internal Ivory design note: There does not seem to be a natural way to provide values for named parameters. The
     * only reasonable solution would be to call <tt>query()</tt> using the "named parameters" proposed for a future
     * PHP version - see https://wiki.php.net/rfc/named_params#what_are_the_benefits_of_named_arguments
     * The current interface is considered as the best from all the bad ones. The obvious disadvantage of the current
     * interface is the named parameter values map may easily be confused with a value for a positional parameter in
     * case the caller forgets to provide any of the positional parameter values. Of course, an alternative way is to
     * avoid specifying all at once, but instead build an {@link SqlRecipe}, fill it with parameter values, and provide
     * it to <tt>query()</tt>. Another alternative would be to use the {@link dataSource()} method and, again, fill that
     * with parameter values.
     *
     * @param string|SqlPattern|IRelationRecipe $sqlFragmentPatternOrRecipe
     * @param array ...$fragmentsAndPositionalParamsAndNamedParamsMap
     * @return IQueryResult
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     */
    function query($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap): IQueryResult;

    /**
     * Sends a command to the database using an SQL pattern, waits for its execution and returns the command result.
     *
     * This is an overloaded method, like {@link query()}. Either:
     * 1. an SQL pattern is given along with values for its parameters, or
     * 2. an {@link SqlRecipe} object is passed, optionally with a map of values for named parameters, or
     * 3. an {@link ICommandRecipe} object is given as the only argument.
     *
     * Note the strict distinction of *queries* and *commands* - see the {@link IStatementExecution} docs. If an SQL
     * query is executed using this method, a result object, reporting as many affected rows as the actual result has,
     * is returned and a warning is issued.
     *
     * @param string|SqlPattern|ICommandRecipe $sqlFragmentPatternOrRecipe
     * @param array ...$fragmentsAndPositionalParamsAndNamedParamsMap
     * @return ICommandResult
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     */
    function command($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap): ICommandResult;

//    function dataSource(); // TODO

    /**
     * Sends a raw SQL query, as is, to the database, waits for its execution and returns the resulting relation.
     *
     * Just a single statement may be used. For sending multiple statements at once, use {@link rawMultiQuery()} or
     * {@link runScript()}.
     *
     * The same distinction of statements to queries and commands applies to this method the same as for
     * {@link query()}, including the resolution of commands executed by this method. For sending commands (i.e.,
     * statements not resulting in a relation), use {@link rawCommand()}.
     *
     * @param string $sqlQuery an SQL query
     * @return IQueryResult the result of the query
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error, or if <tt>$sqlQuery</tt>
     *                              actually contains multiple statements (e.g., separated by a semicolon)
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     */
    function rawQuery(string $sqlQuery): IQueryResult;

    /**
     * Sends a raw SQL command, as is, to the database, waits for its execution and returns the command result.
     *
     * Just a single statement may be used. For sending multiple statements at once, use {@link rawMultiQuery()} or
     * {@link runScript()}.
     *
     * The same distinction of statements to queries and commands applies to this method the same as for
     * {@link command()}, including the resolution of queries executed by this method. For sending queries (i.e.,
     * statements resulting in a relation), use {@link rawQuery()}.
     *
     * @param string $sqlCommand
     * @return ICommandResult the result of the command
     * @throws StatementException when the command is erroneous and PostgreSQL returns an error, or if
     *                              <tt>$sqlCommand</tt> actually contains multiple statements (e.g., separated by a
     *                              semicolon)
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     */
    function rawCommand(string $sqlCommand): ICommandResult;

    /**
     * Sends one or more raw SQL statements to the database, waits for their execution and returns the results.
     *
     * Note this operation, unlike {@link rawQuery()} or {@link rawCommand()}, does *not* distinguish queries and
     * commands. The user has to check for the result type.
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
     * @throws StatementException when some of the statements are erroneous and PostgreSQL returns an error, or if there
     *                            are multiple statements in a single list item
     * @throws ConnectionException when an error occurred while sending the statements or processing the database
     *                             response
     */
    function rawMultiStatement($sqlStatements);

    /**
     * Sends a script of one or more statements to the database, waits for their execution, and returns the results.
     *
     * Note a somewhat counter-intuitive semantics, induced by the semantics of `pg_send_query()`:
     * - an implicit `BEGIN` is issued before the script (if already in a transaction, it has no effect, though);
     * - an implicit `BEGIN` is issued after each explicit `COMMIT` or `ROLLBACK`;
     * - an implicit `COMMIT` is issued after the script unless there was an explicit `BEGIN` in this script with no
     *   following explicit `COMMIT` or `ROLLBACK`, and unless a transaction was already active before running this
     *   script and the script contains neither `COMMIT` nor `ROLLBACK`.
     *
     * @param string $sqlScript a string containing one or more semicolon-separated statements
     * @return IResult[] list of results, one for each statement
     * @throws StatementException when some of the statements are erroneous and PostgreSQL returns an error
     * @throws ConnectionException when an error occurred while sending the statements or processing the database
     *                             response
     */
    function runScript(string $sqlScript);

    /**
     * @return StatementExceptionFactory the local factory used for emitting exceptions upon statement errors
     */
    function getStatementExceptionFactory(): StatementExceptionFactory;
}
