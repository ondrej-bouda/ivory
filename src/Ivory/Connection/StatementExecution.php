<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementException;
use Ivory\Exception\ConnectionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Ivory;
use Ivory\Query\SqlCommandRecipe;
use Ivory\Query\SqlRecipe;
use Ivory\Query\SqlRelationRecipe;
use Ivory\Result\CommandResult;
use Ivory\Result\CopyInResult;
use Ivory\Result\CopyOutResult;
use Ivory\Result\IResult;
use Ivory\Result\QueryResult;

class StatementExecution implements IStatementExecution
{
    private $connCtl;
    private $typeCtl;
    private $stmtExFactory;

    public function __construct(ConnectionControl $connCtl, ITypeControl $typeCtl)
    {
        $this->connCtl = $connCtl;
        $this->typeCtl = $typeCtl;
        $this->stmtExFactory = new StatementExceptionFactory();
    }

    public function query($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap)
    {
        if ($sqlFragmentPatternOrRecipe instanceof SqlRecipe) {
            $recipe = $sqlFragmentPatternOrRecipe;
            if ($fragmentsAndPositionalParamsAndNamedParamsMap) {
                if (count($fragmentsAndPositionalParamsAndNamedParamsMap) > 1) {
                    throw new \InvalidArgumentException('Too many arguments given.');
                }
                $namedParamsMap = $fragmentsAndPositionalParamsAndNamedParamsMap[0];
                $recipe->setParams($namedParamsMap);
            }
        }
        else {
            $recipe = SqlRelationRecipe::fromFragments($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap);
        }

        $sql = $recipe->toSql($this->typeCtl->getTypeDictionary());
        return $this->rawQuery($sql);
    }

    public function command($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap)
    {
        if ($sqlFragmentPatternOrRecipe instanceof SqlRecipe) {
            $recipe = $sqlFragmentPatternOrRecipe;
            if ($fragmentsAndPositionalParamsAndNamedParamsMap) {
                if (count($fragmentsAndPositionalParamsAndNamedParamsMap) > 1) {
                    throw new \InvalidArgumentException('Too many arguments given.');
                }
                $namedParamsMap = $fragmentsAndPositionalParamsAndNamedParamsMap[0];
                $recipe->setParams($namedParamsMap);
            }
        }
        else {
            $recipe = SqlCommandRecipe::fromFragments($sqlFragmentPatternOrRecipe, ...$fragmentsAndPositionalParamsAndNamedParamsMap);
        }

        $sql = $recipe->toSql($this->typeCtl->getTypeDictionary());
        return $this->rawQuery($sql); // FIXME: call rawCommand() instead once it is defined
    }

    public function rawQuery(string $sqlStatement)
    {
        $connHandler = $this->connCtl->requireConnection();

        while (pg_connection_busy($connHandler)) { // just to make things safe, it shall not ever happen
            usleep(1);
        }

        // pg_send_query_params(), as opposed to pg_send_query(), prevents $stmt from containing multiple statements
        $sent = pg_send_query_params($connHandler, $sqlStatement, []); // TODO: consider trapping errors to get more detailed error message
        if (!$sent) {
            throw new ConnectionException('Error sending the query to the database.');
        }

        $res = pg_get_result($connHandler);
        if ($res === false) {
            throw new ConnectionException('No results received from the database.');
        }
        /* For erroneous queries, one must call pg_get_result() once again to update the structures at the client side.
         * Even worse, a loop might actually be needed according to
         * http://www.postgresql.org/message-id/flat/gtitqq$26l3$1@news.hub.org#gtitqq$26l3$1@news.hub.org,
         * which does not sound logical, though, and hopefully was just meant as a generic way to processing results of
         * multiple statements sent in a single pg_send_query() call. Anyway, looping seems to be the safest solution.
         */
        while (pg_get_result($connHandler) !== false) {
            trigger_error('The database gave an unexpected result set.', E_USER_NOTICE);
        }

        $result = $this->processResult($connHandler, $res, $sqlStatement);

        // TODO: emit warning if ICommandResult is returned for rawQuery(), or if IQueryResult is returned for command()

        return $result;
    }

    public function rawMultiQuery($sqlStatements)
    {
        if (!is_array($sqlStatements) && !$sqlStatements instanceof \Traversable) {
            throw new \InvalidArgumentException('$sqlStatements is neither array nor \Traversable object');
        }

        $results = [];
        foreach ($sqlStatements as $stmtKey => $stmt) {
            $results[$stmtKey] = $this->rawQuery($stmt);
        }
        return $results;
    }

    public function runScript($sqlScript)
    {
        $connHandler = $this->connCtl->requireConnection();

        while (pg_connection_busy($connHandler)) { // just to make things safe, it shall not ever happen
            usleep(1);
        }

        $sent = pg_send_query($connHandler, $sqlScript);
        if (!$sent) {
            throw new ConnectionException('Error sending the query to the database.');
        }

        $resHandlers = [];
        while (($res = pg_get_result($connHandler)) !== false) {
            $resHandlers[] = $res; // NOTE: cannot process the result right away - the remaining results must all be read, or they would, in case of error, block the connection from accepting further queries
        }
        $results = [];
        foreach ($resHandlers as $resHandler) {
            $results[] = $this->processResult($connHandler, $resHandler, $sqlScript);
        }
        return $results;
    }

    /**
     * @param resource $connHandler
     * @param resource $resHandler
     * @param string $query
     * @return IResult
     * @throws StatementException upon an SQL statement error
     */
    private function processResult($connHandler, $resHandler, $query)
    {
        $notice = $this->getLastResultNotice();
        $stat = pg_result_status($resHandler);
        switch ($stat) {
            case PGSQL_COMMAND_OK:
                return new CommandResult($resHandler, $notice);
            case PGSQL_TUPLES_OK:
                $typeDict = $this->typeCtl->getTypeDictionary();
                return new QueryResult($resHandler, $typeDict, $notice);
            case PGSQL_COPY_IN:
                return new CopyInResult($connHandler, $resHandler, $notice);
            case PGSQL_COPY_OUT:
                return new CopyOutResult($connHandler, $resHandler, $notice);

            case PGSQL_EMPTY_QUERY:
            case PGSQL_BAD_RESPONSE:
            case PGSQL_NONFATAL_ERROR:
                // non-fatal errors are supposedly not possible to be received by the PHP client library, but anyway...
            case PGSQL_FATAL_ERROR:
                throw $this->stmtExFactory->createException($resHandler, $query, Ivory::getStatementExceptionFactory());

            default:
                throw new \UnexpectedValueException("Unexpected PostgreSQL statement result status: $stat", $stat);
        }
    }

    private function getLastResultNotice()
    {
        $resNotice = pg_last_notice($this->connCtl->requireConnection());
        $connNotice = $this->connCtl->getLastNotice();
        if ($resNotice !== $connNotice) {
            $this->connCtl->setLastNotice($resNotice);
            return $resNotice;
        }
        else {
            return null;
        }
    }

    public function getStatementExceptionFactory()
    {
        return $this->stmtExFactory;
    }
}
