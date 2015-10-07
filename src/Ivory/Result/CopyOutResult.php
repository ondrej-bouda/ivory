<?php
namespace Ivory\Result;

class CopyOutResult extends Result implements ICopyOutResult
{
	public function __construct($handler, $lastNotice = null)
	{
		parent::__construct($handler, $lastNotice);
	}
}
