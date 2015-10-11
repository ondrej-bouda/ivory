<?php
namespace Ivory\Result;

use Ivory\Exception\ConnectionException;

class CopyInResult extends Result implements ICopyInResult
{
	private $connHandler;

	public function __construct($connHandler, $resultHandler, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);

		$this->connHandler = $connHandler;
	}

	public function putLine($line)
	{
		$res = pg_put_line($this->connHandler, $line);
		if ($res === false) {
			throw new ConnectionException('Error sending data to the database server.');
			// TODO: try to squeeze the client to get some useful information; maybe trap errors issued by pg_end_copy()?
		}
	}

	public function end()
	{
		$this->putLine("\\.\n");

		$res = pg_end_copy($this->connHandler);
		if ($res === false) {
			throw new ConnectionException('Error ending copying data to the database server.');
			// TODO: try to squeeze the client to get some useful information; maybe trap errors issued by pg_end_copy()?
		}
	}
}
