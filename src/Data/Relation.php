<?php
namespace Ivory\Data;

class Relation
{
	/** @var \Ivory\IConnection */
	private $connection = null;

	public function setConnection(\Ivory\IConnection $connection)
	{
		$this->connection = $connection;
	}

	public function getConnection()
	{
		if ($this->connection === null) {
			$this->connection = \Ivory\Ivory::
		}

		return $this->connection;
	}
}
