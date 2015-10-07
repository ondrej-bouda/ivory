<?php
namespace Ivory\Result;

class CopyInResult extends Result implements ICopyInResult
{
	public function __construct($handler, $lastNotice = null)
	{
		parent::__construct($handler, $lastNotice);
	}
}
