<?php
namespace Ivory;

trait NamedDbObject
{
    private $schemaName;
    private $name;

    /**
     * @param string $schemaName
     * @param string $name
     */
    protected function setName($schemaName, $name)
    {
        $this->schemaName = $schemaName;
        $this->name = $name;
    }

    /**
     * @return string name of schema this object is defined in
     */
    final public function getSchemaName()
    {
        return $this->schemaName;
    }

    /**
     * @return string name of this object
     */
    final public function getName()
    {
        return $this->name;
    }
}
