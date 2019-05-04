<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\ResultDimensionException;
use Ivory\Exception\StatementException;
use Ivory\Exception\ConnectionException;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Exception\UsageException;
use Ivory\Ivory;
use Ivory\Query\ICommand;
use Ivory\Query\IRelationDefinition;
use Ivory\Query\ISqlPatternStatement;
use Ivory\Query\SqlCommand;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Relation\IColumn;
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
        $this->stmtExFactory = Ivory::getCoreFactory()->createStatementExceptionFactory($this);
    }

    public function query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IQueryResult
    {
        $typeDict = $this->typeCtl->getTypeDictionary();

        try {
            if ($sqlFragmentPatternOrRelationDefinition instanceof ISqlPatternStatement) {
                $this->checkMaxArgs($fragmentsAndParams, 1);
                $sqlRelDef = $sqlFragmentPatternOrRelationDefinition;
                $serializeArgs = $fragmentsAndParams;
                $sql = $sqlRelDef->toSql($typeDict, ...$serializeArgs);
            } elseif ($sqlFragmentPatternOrRelationDefinition instanceof IRelationDefinition) {
                $this->checkMaxArgs($fragmentsAndParams, 0);
                $relDef = $sqlFragmentPatternOrRelationDefinition;
                $sql = $relDef->toSql($typeDict);
            } else {
                $relDef = SqlRelationDefinition::fromFragments(
                    $sqlFragmentPatternOrRelationDefinition,
                    ...$fragmentsAndParams
                );
                $sql = $relDef->toSql($typeDict);
            }
        } catch (InvalidStateException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        return $this->rawQuery($sql);
    }

    public function querySingleTuple($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): ?ITuple
    {
        $rel = $this->query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);
        switch ($rel->count()) {
            case 1:
                return $rel->tuple();
            case 0:
                return null;
            default:
                throw new ResultDimensionException(
                    "The query should have resulted in at most one row, but has {$rel->count()} rows."
                );
        }
    }

    public function querySingleColumn($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams): IColumn
    {
        $rel = $this->query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);

        $colCnt = count($rel->getColumns());
        if ($colCnt != 1) {
            throw new ResultDimensionException(
                "The query should have resulted in exactly one column, but has $colCnt columns."
            );
        }

        return $rel->col(0);
    }

    public function querySingleValue($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams)
    {
        $rel = $this->query($sqlFragmentPatternOrRelationDefinition, ...$fragmentsAndParams);

        $colCnt = count($rel->getColumns());
        if ($colCnt != 1) {
            throw new ResultDimensionException(
                "The query should have resulted in exactly one column, but has $colCnt columns."
            );
        }

        switch ($rel->count()) {
            case 1:
                return $rel->value();
            case 0:
                return null;
            default:
                throw new ResultDimensionException(
                    "The query should have resulted in at most one row, but has {$rel->count()} rows."
                );
        }
    }

    public function command($sqlFragmentPatternOrCommand, ...$fragmentsAndParams): ICommandResult
    {
        $typeDict = $this->typeCtl->getTypeDictionary();

        try {
            if ($sqlFragmentPatternOrCommand instanceof ISqlPatternStatement) {
                $this->checkMaxArgs($fragmentsAndParams, 1);
                $sqlCommand = $sqlFragmentPatternOrCommand;
                $serializeArgs = $fragmentsAndParams;
                $sql = $sqlCommand->toSql($typeDict, ...$serializeArgs);
            } elseif ($sqlFragmentPatternOrCommand instanceof ICommand) {
                $this->checkMaxArgs($fragmentsAndParams, 0);
                $command = $sqlFragmentPatternOrCommand;
                $sql = $command->toSql($typeDict);
            } else {
                $command = SqlCommand::fromFragments($sqlFragmentPatternOrCommand, ...$fragmentsAndParams);
                $sql = $command->toSql($typeDict);
            }
        } catch (InvalidStateException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        return $this->rawCommand($sql);
    }

    private function checkMaxArgs($args, int $maxCount): void
    {
        if (count($args) > $maxCount) {
            throw new \InvalidArgumentException('Too many arguments given.');
        }
    }

    public function rawQuery(string $sqlQuery): IQueryResult
    {
        $result = $this->executeRawStatement($sqlQuery);
        if ($result instanceof IQueryResult) {
            return $result;
        } else {
            throw new UsageException(
                'The supplied SQL statement was supposed to be a query, but it did not return a result set. ' .
                'Did you mean to call command() or rawCommand()?'
            );
        }
    }

    public function rawCommand(string $sqlCommand): ICommandResult
    {
        $result = $this->executeRawStatement($sqlCommand);
        if ($result instanceof ICommandResult) {
            return $result;
        } else {
            throw new UsageException(
                'The supplied SQL statement was supposed to be a command, but it returned a result set. ' .
                'Did you mean to call query() or rawQuery()?'
            );
        }
    }

    public function executeStatement($sqlStatement): IResult
    {
        if (is_string($sqlStatement)) {
            $rawStatement = $sqlStatement;
        } elseif ($sqlStatement instanceof ISqlPatternStatement) {
            $typeDict = $this->typeCtl->getTypeDictionary();
            $rawStatement = $sqlStatement->toSql($typeDict);
        } else {
            throw new \InvalidArgumentException(
                '$sqlStatement must either by a string or ' . ISqlPatternStatement::class
            );
        }

        return $this->executeRawStatement($rawStatement);
    }

    private function executeRawStatement(string $sqlStatement): IResult
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
        /**
         * For erroneous queries, one must call pg_get_result() once again to update the structures at the client side.
         * Even worse, a loop might actually be needed according to
         * {@link http://www.postgresql.org/message-id/flat/gtitqq$26l3$1@news.hub.org#gtitqq$26l3$1@news.hub.org},
         * which does not sound logical, though, and hopefully was just meant as a generic way to processing results of
         * multiple statements sent in a single pg_send_query() call. Anyway, looping seems to be the safest solution.
         */
        while (pg_get_result($connHandler) !== false) {
            trigger_error('The database gave an unexpected result set.', E_USER_NOTICE);
        }

        return $this->processResult($connHandler, $resultHandler, $sqlStatement);
    }

    public function runScript(string $sqlScript): array
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
                return new QueryResult($resHandler, $this->typeCtl, $notice);
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

    private function getLastResultNotice(): ?string
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
