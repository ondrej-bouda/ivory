<?php
declare(strict_types=1);
namespace Ivory\Result;

interface IAsyncCommandResult
{
    /**
     * Waits for the command result to be retrieved from the database, and returns it.
     */
    function getResult(): ICommandResult;
}
