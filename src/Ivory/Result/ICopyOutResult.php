<?php
namespace Ivory\Result;

use Ivory\Exception\ConnectionException;

/**
 * Result of a successful `COPY TO STDOUT` statement, i.e., dump of a relation sent directly to the client.
 *
 * Note that until {@ICopyOutResult::end()} method is called, the connection is blocked for further statements.
 */
interface ICopyOutResult extends IResult
{
    /* NOTE: Currently, there does not seem to be any function being able to read the data copied from PostgreSQL using
     *       stdout - as of PHP 7.0, there is no code in the pgsql extension using PQgetline() except pg_copy_to(),
     *       which does the whole job at once, without too much flexibility, though.
     */

    /**
     * Finalizes copying data from the database.
     *
     * @throws ConnectionException if the connection got closed or broken
     */
    function end();
}
