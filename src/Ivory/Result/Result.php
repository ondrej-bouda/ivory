<?php
declare(strict_types=1);
namespace Ivory\Result;

abstract class Result implements IResult
{
    /** @var resource result handler */
    protected $handler;
    /** @var string|null last PostgreSQL notice issued by when returning this result */
    private $lastNotice;

    protected function __construct($resultHandler, ?string $lastNotice = null)
    {
        if (!is_resource($resultHandler) && !$resultHandler instanceof \PgSql\Result) {
            throw new \InvalidArgumentException('$handler');
        }
        $this->handler = $resultHandler;
        $this->lastNotice = $lastNotice;
    }

    final public function getLastNotice(): ?string
    {
        return $this->lastNotice;
    }

    final public function getCommandTag(): string
    {
        return pg_result_status($this->handler, PGSQL_STATUS_STRING);
    }
}
