<?php
namespace Ivory\Connection;

interface ICommandExecution
{
    /**
     * Sends an SQL query to the database, waits for its execution and returns the result.
     *
     * @param string $sql
     * @return \Ivory\Command\IResult
     */
    function query($sql);
}
