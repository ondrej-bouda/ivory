<?php
declare(strict_types=1);
namespace Ivory\Result;

class AsyncResult implements IAsyncResult
{
    private $resultFetcher;

    public function __construct(\Closure $resultFetcher)
    {
        $this->resultFetcher = $resultFetcher;
    }

    public function getResult(): IResult
    {
        $result = ($this->resultFetcher)();
        if ($result instanceof IResult) {
            return $result;
        } else {
            throw new \LogicException('The result fetcher did not return an expected result.');
        }
    }
}
