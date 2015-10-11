<?php
namespace Ivory\Result;

class CommandResult extends Result implements ICommandResult
{
	public function __construct($resultHandler, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);
	}

	public function getAffectedRows()
	{
		// TODO: Implement getAffectedRows() method.
	}
}
