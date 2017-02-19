<?php
namespace Ivory\Connection;

use Ivory\Exception\ResultException;
use Ivory\Exception\StatementException;
use Ivory\Exception\ConnectionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Ivory;
use Ivory\Query\ICommandRecipe;
use Ivory\Query\IRelationRecipe;
use Ivory\Query\ISqlPatternRecipe;
use Ivory\Query\SqlCommandRecipe;
use Ivory\Query\SqlRelationRecipe;
use Ivory\Relation\ITuple;
use Ivory\Result\CommandResult;
use Ivory\Result\CopyInResult;
use Ivory\Result\CopyOutResult;
use Ivory\Result\ICommandResult;
use Ivory\Result\IQueryResult;
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

    public function query($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): IQueryResult
    {
        if ($sqlFragmentPatternOrRecipe instanceof IRelationRecipe) {
            $recipe = $this->setupRecipe($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        } else {
            $recipe = SqlRelationRecipe::fromFragments($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        }

        $sql = $recipe->toSql($this->typeCtl->getTypeDictionary());
        return $this->rawQuery($sql);
    }

    public function querySingleTuple($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): ITuple
    {
        $rel = $this->query($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        if ($rel->count() != 1) {
            throw new ResultException(
                "The query should have resulted in exactly one row, but has {$rel->count()} rows."
            );
        }
        return $rel->tuple();
    }

    public function querySingleValue($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams)
    {
        $rel = $this->query($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        if ($rel->count() != 1) {
            throw new ResultException(
                "The query should have resulted in exactly one row, but has {$rel->count()} rows."
            );
        }
        $colCnt = count($rel->getColumns());
        if ($colCnt != 1) {
            throw new ResultException(
                "The query should have resulted in exactly one column, but has $colCnt columns."
            );
        }
        return $rel->value();
    }

    public function command($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams): ICommandResult
    {
        if ($sqlFragmentPatternOrRecipe instanceof ICommandRecipe) {
            $recipe = $this->setupRecipe($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        } else {
            $recipe = SqlCommandRecipe::fromFragments($sqlFragmentPatternOrRecipe, ...$fragmentsAndParams);
        }

        $sql = $recipe->toSql($this->typeCtl->getTypeDictionary());
        return $this->rawCommand($sql);
    }

    private function setupRecipe($recipe, ...$args)
    {
        if ($args) {
            if ($recipe instanceof ISqlPatternRecipe) {
                if (count($args) > 1) {
                    throw new \InvalidArgumentException('Too many arguments given.');
                }
                $namedParamsMap = $args[0];
                $recipe->setParams($namedParamsMap);
            } else {
                throw new \InvalidArgumentException('Too many arguments given.');
            }
        }

        return $recipe;
    }

    public function rawQuery(string $sqlQuery): IQueryResult
    {
        $result = $this->executeRawStatement($sqlQuery, $resultHandler);
        if ($result instanceof IQueryResult) {
            return $result;
        } else {
            trigger_error(
                'The supplied SQL statement was supposed to be a query, but it did not return a result set. ' .
                'Returning an empty relation. Consider calling command() or rawCommand() instead. ' .
                "SQL statement: $sqlQuery",
                E_USER_WARNING
            );
            return new QueryResult($resultHandler, $this->typeCtl->getTypeDictionary(), $result->getLastNotice());
        }
    }

    public function rawCommand(string $sqlCommand): ICommandResult
    {
        $result = $this->executeRawStatement($sqlCommand, $resultHandler);
        if ($result instanceof ICommandResult) {
            return $result;
        } else {
            trigger_error(
                'The supplied SQL statement was supposed to be a command, but it returned a result set. ' .
                'Returning an empty result. Consider calling query() or rawQuery() instead. ' .
                "SQL statement: $sqlCommand",
                E_USER_WARNING
            );
            return new CommandResult($resultHandler, $result->getLastNotice());
        }
    }

    private function executeRawStatement(string $sqlStatement, &$resultHandler = null): IResult
    {
        $connHandler = $this->connCtl->requireConnection();

        while (pg_connection_busy($connHandler)) { // just to make things safe, it shall not ever happen
            usleep(1);
        }

        // pg_send_query_params(), as opposed to pg_send_query(), prevents $stmt from containing multiple statements
        $sent = pg_send_query_params($connHandler, $sqlStatement, []);
        if (!$sent) {
            // TODO: consider trapping errors to get more detailed error message
            throw new ConnectionException('Error sending the query to the database.');
        }

        $resultHandler = pg_get_result($connHandler);
        if ($resultHandler === false) {
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

        return $this->processResult($connHandler, $resultHandler, $sqlStatement);
    }

    public function rawMultiStatement($sqlStatements)
    {
        if (!is_array($sqlStatements) && !$sqlStatements instanceof \Traversable) {
            throw new \InvalidArgumentException('$sqlStatements is neither array nor \Traversable object');
        }

        $results = [];
        foreach ($sqlStatements as $stmtKey => $stmt) {
            $results[$stmtKey] = $this->executeRawStatement($stmt);
        }
        return $results;
    }

    public function runScript(string $sqlScript)
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
            /* NOTE: Cannot process the result right away - the remaining results must all be read, or they would, in
             * case of error, block the connection from accepting further queries.
             */
            $resHandlers[] = $res;
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
    private function processResult($connHandler, $resHandler, string $query): IResult
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
        } else {
            return null;
        }
    }

    public function getStatementExceptionFactory(): StatementExceptionFactory
    {
        return $this->stmtExFactory;
    }
}
