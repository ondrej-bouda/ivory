<?php
namespace Ivory\Result;

// TODO: discover how copying works; maybe CommandResult::getAffectedRows() cannot return the number of rows COPIED...
class CopyResult extends Result implements ICopyResult
{
	public function __construct($handler, $lastNotice = null)
	{
		parent::__construct($handler, $lastNotice);
	}
}
