<?php
declare(strict_types=1);
namespace Ivory\Result;

interface IAsyncResult
{
    /**
     * Waits for the result to be retrieved from the database, and returns it.
     */
    function getResult(): IResult;
}
