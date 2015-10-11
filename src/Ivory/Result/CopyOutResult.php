<?php
namespace Ivory\Result;

use Ivory\Exception\ConnectionException;

class CopyOutResult extends Result implements ICopyOutResult
{
	private $connHandler;

	public function __construct($connHandler, $resultHandler, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);

		$this->connHandler = $connHandler;
	}

	public function end()
	{
		$res = pg_end_copy($this->connHandler);
		if ($res === false) {
			throw new ConnectionException('Error ending copying data from the database server.');
			// TODO: try to squeeze the client to get some useful information; maybe trap errors issued by pg_end_copy()?
		}
	}
}
