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
    protected function setName(string $schemaName, string $name)
    {
        $this->schemaName = $schemaName;
        $this->name = $name;
    }

    /**
     * @return string name of schema this object is defined in
     */
    final public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    /**
     * @return string name of this object
     */
    final public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string schema-qualified name of this object
     */
    final public function getQualifiedName(): string
    {
        return $this->getSchemaName() . '.' . $this->getName();
    }
}
