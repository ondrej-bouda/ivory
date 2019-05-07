<?php
declare(strict_types=1);
namespace Ivory\Result;

use Ivory\Exception\UsageException;

class AsyncQueryResult implements IAsyncQueryResult
{
    private $asyncResult;

    public function __construct(\Closure $resultFetcher)
    {
        $this->asyncResult = new AsyncResult($resultFetcher);
    }

    public function getResult(): IQueryResult
    {
        $result = $this->asyncResult->getResult();
        if ($result instanceof IQueryResult) {
            return $result;
        } else {
            throw new UsageException(
                'The supplied SQL statement was supposed to be a query, but it did not return a result set. ' .
                'Did you mean to call commandAsync()?'
            );
        }
    }
}
