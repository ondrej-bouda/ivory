<?php
namespace Ivory\Result;

class QueryResult extends Result implements IQueryResult
{
	public function __construct($handler, $lastNotice = null)
	{
		parent::__construct($handler, $lastNotice);
	}
}
