<?php
declare(strict_types=1);
namespace Ivory\Result;

use Ivory\Exception\UsageException;

class AsyncCommandResult implements IAsyncCommandResult
{
    private $asyncResult;

    public function __construct(\Closure $resultFetcher)
    {
        $this->asyncResult = new AsyncResult($resultFetcher);
    }

    public function getResult(): ICommandResult
    {
        $result = $this->asyncResult->getResult();
        if ($result instanceof ICommandResult) {
            return $result;
        } else {
            throw new UsageException(
                'The supplied SQL statement was supposed to be a command, but it returned a result set. ' .
                'Did you mean to call queryAsync()?'
            );
        }
    }
}
