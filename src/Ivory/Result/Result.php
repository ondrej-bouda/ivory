<?php
namespace Ivory\Result;

abstract class Result implements IResult
{
    /** @var resource result handler */
    protected $handler;
    /** @var string last PostgreSQL notice issued by when returning this result */
    private $lastNotice;

    protected function __construct($handler, $lastNotice = null)
    {
        if (!is_resource($handler)) {
            throw new \InvalidArgumentException('$handler');
        }
        $this->handler = $handler;
        $this->lastNotice = $lastNotice;
    }

    final public function getLastNotice()
    {
        return $this->lastNotice;
    }

    final public function getCommandTag()
    {
        return pg_result_status($this->handler, PGSQL_STATUS_STRING);
    }
}
