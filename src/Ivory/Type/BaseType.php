<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

abstract class BaseType implements INamedType
{
	private $name;
	private $schemaName;
	private $connection;

	public function __construct($name, $schemaName, IConnection $connection)
	{
		$this->name = $name;
		$this->schemaName = $schemaName;
		$this->connection = $connection;
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getSchemaName()
	{
		return $this->schemaName;
	}

	final protected function getConnection()
	{
		return $this->connection;
	}

	protected function throwInvalidValue($str, \Exception $cause = null)
	{
		$message = "Value '$str' is not valid for type {$this->schemaName}.{$this->name}";
		throw new \InvalidArgumentException($message, 0, $cause);
	}
}
