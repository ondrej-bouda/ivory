<?php
namespace Ivory\Connection;

use Ivory\Command\Result;
use Ivory\Exception\CommandException;
use Ivory\Exception\ConnectionException;

class CommandExecution implements ICommandExecution
{
    private $connCtl;

    public function __construct(ConnectionControl $connCtl)
    {
        $this->connCtl = $connCtl;
    }

    public function rawQuery($sqlStatement)
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

        return $this->processResult($res, $sqlStatement);
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
            $results[] = $this->processResult($resHandler, $sqlScript);
        }
        return $results;
    }

    private function processResult($resHandler, $query)
    {
        $stat = pg_result_status($resHandler);
        switch ($stat) {
            case PGSQL_COMMAND_OK:
            case PGSQL_TUPLES_OK:
            case PGSQL_COPY_IN: // TODO: is COPY IN/OUT blocking somehow? blocking the execution of further commands? keeping the connection busy (!) even after pg_get_result()?
            case PGSQL_COPY_OUT:
                /* TODO: use pg_last_notice() to get the last notice.
                         Unfortunately, it seems the client library is pretty limited:
                         - there is no other way of getting notices of successful commands than using get_last_notice();
                         - it only reports the last notice - thus, all but the last notice of a single successful
                           statement cannot be caught by any means;
                         - there is no clearing mechanism, thus, successful command emitting the same notice as the
                           previous command is indistinguishable from a command emitting nothing; commands with no
                           notice do not clear the notice returned by get_last_notice(); by the way, it is
                           connection-wide, thus, notion of last received notice must be kept on the whole connection,
                           and a notice found out by get_last_notice() should only be reported if different from the
                           last one.
                 */
                return new Result($resHandler, $stat); // TODO: differentiate the result by the result status - for TUPLES OK, collect the result set column names and types etc.

            case PGSQL_EMPTY_QUERY:
            case PGSQL_BAD_RESPONSE:
            case PGSQL_NONFATAL_ERROR:
                // non-fatal errors are supposedly not possible to be received by the PHP client library, but anyway...
            case PGSQL_FATAL_ERROR:
                throw new CommandException($resHandler, $query);

            default:
                throw new \UnexpectedValueException("Unexpected PostgreSQL command result status: $stat", $stat);
        }
    }
}
