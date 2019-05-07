<?php
declare(strict_types=1);
namespace Ivory\Result;

interface IAsyncQueryResult
{
    /**
     * Waits for the query result to be retrieved from the database, and returns it.
     */
    function getResult(): IQueryResult;
}
