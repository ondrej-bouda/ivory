<?php
namespace Ivory\Result;

class CommandResult extends Result implements ICommandResult
{
    public function __construct($resultHandler, string $lastNotice = null)
    {
        parent::__construct($resultHandler, $lastNotice);
    }

    public function getAffectedRows()
    {
        return pg_affected_rows($this->handler);
    }
}
