<?php
namespace Ivory\Type;

abstract class BaseType implements INamedType
{
	private $name;
	private $schemaName;

	public function __construct($name, $schemaName)
	{
		$this->name = $name;
		$this->schemaName = $schemaName;
	}

	final public function getName()
	{
		return $this->name;
	}

	final public function getSchemaName()
	{
		return $this->schemaName;
	}

	protected function throwInvalidValue($str, \Exception $cause = null)
	{
		$message = "Value '$str' is not valid for type {$this->schemaName}.{$this->name}";
		throw new \InvalidArgumentException($message, 0, $cause);
	}
}
