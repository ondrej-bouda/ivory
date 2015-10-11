<?php
namespace Ivory\Result;

class QueryResult extends Result implements IQueryResult
{
	public function __construct($resultHandler, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);
	}
}
