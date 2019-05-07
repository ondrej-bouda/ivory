<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\ResultDimensionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Exception\UsageException;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Query\ICommand;
use Ivory\Query\IRelationDefinition;
use Ivory\Query\ISqlPatternStatement;
use Ivory\Relation\IColumn;
use Ivory\Relation\ITuple;
use Ivory\Result\IAsyncCommandResult;
use Ivory\Result\IAsyncQueryResult;
use Ivory\Result\IAsyncResult;
use Ivory\Result\ICommandResult;
use Ivory\Result\IQueryResult;
use Ivory\Result\IResult;
use Ivory\Exception\StatementException;
use Ivory\Exception\ConnectionException;

/**
 * Execution of statements on the connected database.
 *
 * For type safety and transparency reasons, Ivory strictly distinguishes the executed statements to:
 * - *queries*, which result in a relation, and
 * - *commands*, the result of which is just a summary information, such as the number of affected rows in a table.
 *
 * Using the {@link IStatementExecution::query()} method with an SQL statement not returning data set (such as a plain
 * `INSERT` without the `RETURNING` clause) is an error, as well as calling {@link IStatementExecution::command()} with
 * an SQL statement returning data (such as `SELECT`), and will result in a {@link UsageException}.
 *
 * _Ivory design note: Traditionally, in other database layers (including the pgsql extension), methods like query()
 * serve both for reading data from the database and for writing data to it, requiring the user to use the results of
 * such a query() method in the right way. The approach applied in Ivory is not to confuse the terms by defining a
 * *query()* method documented as "Executes an SQL statement", but rather to enhance type safety and promote code
 * clarity. Thus the separation in two distinct methods, {@link IStatementExecution::query()} and
 * {@link IStatementExecution::command()}. After all, processing the result of either of them is fairly different from
 * the result of the other one. And even if one changes a plain <tt>INSERT</tt> to <tt>INSERT ... RETURNING</tt>, the
 * change from <tt>query()</tt> to <tt>command()</tt> will be the tiniest adjustment of the related source code._
 *
 * _Ivory design note: Originally, intertwined <tt>query()</tt> and <tt>command()</tt> were handled gracefully, issuing
 * a mere warning and returning a substitute object (empty relation). A stricter approach is applied, now, to promote
 * the strict separation of the two families of methods, as it clearly signifies a wrong code (e.g., specific parameter
 * values are irrelevant in the question of whether the statement is a query or a command)._
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
     * 2. The first argument is an {@link ISqlPatternStatement} object (such as {@link SqlRelationDefinition}). No
     *    positional arguments are expected in this case, only the map of values for named parameters if present. As the
     *    `SqlRelationDefinition` might have already set all its named parameters, even this argument might not be
     *    necessary. Note that values of named parameters passed as argument to this method have precedence over those
     *    already set on the `SqlRelationDefinition` object. The object is untouched by this method, just the same-named
     *    parameter value is ineffective.
     * 3. The first and the only argument is an {@link IRelationDefinition} object.
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
     * command is executed using this method, a {@link UsageException} is thrown after the command execution.
     *
     * If executing the query immediately is not appropriate, consider using {@link dataSource()} instead.
     *
     * _Ivory design note: There does not seem to be a natural way to provide values for named parameters. The only
     * reasonable solution would be to call <tt>query()</tt> using the "named parameters"
     * {@link https://wiki.php.net/rfc/named_params#what_are_the_benefits_of_named_arguments proposed} for a future PHP
     * version.
     * The current interface is considered as the best from all the bad ones. The obvious disadvantage of the current
     * interface is the named parameter values map may easily be confused with a value for a positional parameter in
     * case the caller forgets to provide any of the positional parameter values. Of course, an alternative way is to
     * avoid specifying all at once, but instead build an {@link IRelationDefinition}, fill it with parameter values,
     * and provide it to <tt>query()</tt>. Another alternative would be to use the {@link dataSource()} method and,
     * again, fill that with parameter values._
     *
     * @param string|SqlPattern|IRelationDefinition $sqlFragmentPatternOrRelationDefinition
     * @param array ...$fragmentsAndParams
     * @return IQueryResult
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     * @throws UsageException if the statement appears to be a command rather than a query
     */
    function query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IQueryResult;

    /**
     * Queries the database for a relation using an SQL pattern and returns without waiting. The resulting relation may
     * then be retrieved when needed.
     *
     * This is an asynchronous variant of {@link query()}.
     *
     * Instead of a result object, which would normally be returned by {@link query()}, an {@link IAsyncQueryResult} is
     * returned. Once the query result is needed, call `getResult()` on the object, which will return an `IQueryResult`.
     *
     * Until calling `getResult()`, avoid sending any other statements to the database within the same connection.
     * Otherwise, the results might get mixed.
     *
     * Example:
     * <code>
     * $asyncResult = $connection->queryAsync('SELECT pg_sleep(2), 3.14'); // an expensive query
     * // immediately returned
     * sleep(2); // some useful computation on the PHP side; avoid querying the database within the same connection!
     * $result = $asyncResult->getResult(); // now we get the results
     * assert($result instanceof IQueryResult); // ...and we can work with it as usual
     * </code>
     *
     * @param string|SqlPattern|IRelationDefinition $sqlFragmentPatternOrRelationDefinition
     * @param array ...$fragmentsAndParams
     * @return IAsyncQueryResult encapsulation of the result
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     * @throws UsageException if the statement appears to be a command rather than a query
     * @see query() for more details on the arguments
     */
    function queryAsync($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IAsyncQueryResult;

    /**
     * Queries the database for a relation using an SQL pattern, checks it results in at most one row, and returns it.
     *
     * The base functionality is the same as in {@link query()}. On top of that, the result is checked for the number of
     * rows:
     * - if the result consists of exactly one row, it is returned;
     * - in case there are more rows, a {@link ResultDimensionException} is thrown as it marks a program logic error;
     * - if no rows are in the result, `null` is returned as that might be a regular case that, depending on the actual
     *   data, there is no row.
     *
     * @see query() for detailed specification
     * @param string|SqlPattern|IRelationDefinition $sqlFragmentPatternOrRelationDefinition
     * @param array ...$fragmentsAndParams
     * @return ITuple|null the single row from the result, or <tt>null</tt> if there are is no data
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ResultDimensionException when the resulting data set has more than one row, or no row at all
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     * @throws UsageException if the statement appears to be a command rather than a query
     */
    function querySingleTuple($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): ?ITuple;

    /**
     * Queries the database for a relation using an SQL pattern, checks it results in exactly one column, and returns
     * it.
     *
     * The base functionality is the same as in {@link query()}. On top of that, the result is checked for having
     * exactly one column, and the column is returned.
     *
     * Note that, unlike {@link querySingleTuple()}, even zero columns in the result lead to a
     * {@link ResultDimensionException} as it obviously marks a program logic error.
     *
     * @see query() for detailed specification
     * @param string|SqlPattern|IRelationDefinition $sqlFragmentPatternOrRelationDefinition
     * @param array ...$fragmentsAndParams
     * @return IColumn
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ResultDimensionException when the resulting data set has more than one column, or no column at all
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     * @throws UsageException if the statement appears to be a command rather than a query
     */
    function querySingleColumn($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IColumn;

    /**
     * Queries the database for a relation using an SQL pattern, checks it results in at most one row with exactly one
     * column, and returns the value from it.
     *
     * The base functionality is the same as in {@link query()}. On top of that, the result is checked for the number of
     * rows and columns:
     * - if the results consists of exactly one column and one row, the single cell is returned;
     * - if there is no data, but the result structurally has exactly one column, `null` is returned;
     * - in other cases (i.e., more than one row or not exactly one column), a {@link ResultDimensionException} is
     *   thrown as it marks a program logic error.
     *
     * @param string|SqlPattern|IRelationDefinition $sqlFragmentPatternOrRelationDefinition
     * @param array ...$fragmentsAndParams
     * @return mixed value of the only row from the only column the relation has, or <tt>null</tt> if there is no data
     * @throws StatementException when the query is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ResultDimensionException when the resulting data set has more than one row, or more than one column, or
     *                                    no column at all
     * @throws ConnectionException when an error occurred while sending the query or processing the database response
     * @throws UsageException if the statement appears to be a command rather than a query
     *@see query() for detailed specification
     */
    function querySingleValue($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);

    /**
     * Sends a command to the database using an SQL pattern, waits for its execution and returns the command result.
     *
     * This is an overloaded method, like {@link query()}. Either:
     * 1. an SQL pattern is given along with values for its parameters, or
     * 2. an {@link ISqlPatternStatement} object is passed, optionally with a map of values for named parameters, or
     * 3. an {@link ICommand} object is given as the only argument.
     *
     * Note the strict distinction of *queries* and *commands* - see the {@link IStatementExecution} docs. If an SQL
     * query is executed using this method, a {@link UsageException} is thrown after the query execution.
     *
     * @param string|SqlPattern|ICommand $sqlFragmentPatternOrCommand
     * @param array ...$fragmentsAndParams
     * @return ICommandResult
     * @throws StatementException when the command is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     * @throws UsageException if the statement appears to be a query rather than a command
     */
    function command($sqlFragmentPatternOrCommand, ...$fragmentsAndParams): ICommandResult;

    /**
     * Sends a command to the database using an SQL pattern and returns without waiting. The command result may then be
     * retrieved when needed.
     *
     * This is an asynchronous variant of {@link command()}.
     *
     * @param string|SqlPattern|ICommand $sqlFragmentPatternOrCommand
     * @param array ...$fragmentsAndParams
     * @return IAsyncCommandResult encapsulation of the result
     * @throws StatementException when the command is erroneous and PostgreSQL returns an error
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     * @throws UsageException if the statement appears to be a query rather than a command
     * @see command() for more details on the arguments
     * @see queryAsync() for more details on how to get the result
     */
    function commandAsync($sqlFragmentPatternOrCommand, ...$fragmentsAndParams): IAsyncCommandResult;

    /**
     * Sends a raw SQL query, as is, to the database, waits for its execution and returns the resulting relation.
     *
     * Just a single statement may be used. For sending multiple statements at once, use {@link runScript()}.
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
     * @throws UsageException if the statement appears to be a command rather than a query
     */
    function rawQuery(string $sqlQuery): IQueryResult;

    /**
     * Sends a raw SQL command, as is, to the database, waits for its execution and returns the command result.
     *
     * Just a single statement may be used. For sending multiple statements at once, use {@link runScript()}.
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
     * @throws UsageException if the statement appears to be a query rather than a command
     */
    function rawCommand(string $sqlCommand): ICommandResult;

    /**
     * Sends an SQL statement to the database, waits for its execution, and returns the result.
     *
     * This is a generic variant which does not distinguish queries from commands. The expected usage is an SQL string
     * received from input, i.e., when the program itself does not know whether the statement is actually a query or
     * command.
     *
     * The statement must be given either as:
     * - a string containing raw SQL, which is sent to the database as is; or
     * - an {@link ISqlPatternStatement}, which is serialized to SQL using {@link ISqlPatternStatement::toSql()} with no
     *   parameter values, i.e., all the parameters must already be set on the object.
     *
     * @param string|ISqlPatternStatement $sqlStatement either a raw SQL string, or {@link ISqlPatternStatement}
     * @return IResult the result of the statement
     * @throws StatementException when the statement is erroneous and PostgreSQL returns an error, or if
     *                              <tt>$sqlStatement</tt> actually contains multiple statements (e.g., separated by a
     *                              semicolon)
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     */
    function executeStatement($sqlStatement): IResult;

    /**
     * Sends an SQL statement to the database and returns without waiting. The result may then be retrieved when needed.
     *
     * This is an asynchronous variant of {@link executeStatement()}.
     *
     * @param string|ISqlPatternStatement $sqlStatement either a raw SQL string, or {@link ISqlPatternStatement}
     * @return IAsyncResult encapsulation of the result
     * @throws StatementException when the statement is erroneous and PostgreSQL returns an error, or if
     *                              <tt>$sqlStatement</tt> actually contains multiple statements (e.g., separated by a
     *                              semicolon)
     * @throws ConnectionException when an error occurred while sending the command or processing the database response
     * @see executeStatement() for more details on the arguments
     * @see queryAsync() for more details on how to get the result
     */
    function executeStatementAsync($sqlStatement): IAsyncResult;

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
     * @return IResult[] list of results, one for each executed statement
     * @throws StatementException when some of the statements are erroneous and PostgreSQL returns an error
     * @throws ConnectionException when an error occurred while sending the statements or processing the database
     *                             response
     */
    function runScript(string $sqlScript): array;

    /**
     * @return StatementExceptionFactory the local factory used for emitting exceptions upon statement errors
     */
    function getStatementExceptionFactory(): StatementExceptionFactory;
}
