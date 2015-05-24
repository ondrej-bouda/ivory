<?php
namespace Ivory;

trait NamedDbObject
{
	private $name;
	private $schemaName;

	/**
	 * @param string $name
	 * @param string $schemaName
	 */
	protected function setName($name, $schemaName)
	{
		$this->name = $name;
		$this->schemaName = $schemaName;
	}

	/**
	 * @return string name of this object
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string name of schema this object is defined in
	 */
	public function getSchemaName()
	{
		return $this->schemaName;
	}
}
