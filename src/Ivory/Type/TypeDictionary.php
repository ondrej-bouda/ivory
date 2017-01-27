<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

/**
 * Dictionary of data types on a concrete database.
 */
class TypeDictionary implements ITypeDictionary
{
    /** @var IType[] */
    private $oidTypeMap = [];
    /** @var INamedType[] */
    private $nameTypeMap = [];
    /** @var ITypeDictionaryUndefinedHandler|null */
    private $undefinedTypeHandler = null;

    public function defineType($oid, IType $type)
    {
        $this->oidTypeMap[$oid] = $type;

        if ($type instanceof INamedType) {
            $name = $type->getSchemaName() . '.' . $type->getName();
            $this->nameTypeMap[$name] = $type;
        }
    }

    public function defineCustomType(string $name, IType $type)
    {
        $this->nameTypeMap[$name] = $type;
    }

    public function defineTypeAlias(string $alias, string $typeName)
    {
        $this->nameTypeMap[$alias] =& $this->nameTypeMap[$typeName]; // FIXME: $this->nameTypeMap must be split in two structures to distinguish an alias type "some.type" from fully-qualified type names
    }

    /**
     * @return ITypeDictionaryUndefinedHandler|null
     */
    public function getUndefinedTypeHandler()
    {
        return $this->undefinedTypeHandler;
    }

    /**
     * @param ITypeDictionaryUndefinedHandler|null $undefinedTypeHandler
     */
    public function setUndefinedTypeHandler($undefinedTypeHandler)
    {
        $this->undefinedTypeHandler = $undefinedTypeHandler;
    }

    public function requireTypeByOid($oid) : IType
    {
        if (isset($this->oidTypeMap[$oid])) {
            return $this->oidTypeMap[$oid];
        }

        if ($this->undefinedTypeHandler !== null) {
            $type = $this->undefinedTypeHandler->handleUndefinedType($oid, null, null);
            if ($type !== null) {
                return $type;
            }
        }

        throw new UndefinedTypeException("There is no type defined for OID $oid");
    }

    public function requireTypeByName($name) : IType
    {
        if (isset($this->nameTypeMap[$name])) {
            return $this->nameTypeMap[$name];
        }

        if ($this->undefinedTypeHandler !== null) {
            $type = $this->undefinedTypeHandler->handleUndefinedType(null, $name, null);
            if ($type !== null) {
                return $type;
            }
        }

        throw new UndefinedTypeException("There is no type defined for name \"$name\"");
    }

    public function requireTypeByValue($value) : IType
    {
        throw new \Ivory\Exception\NotImplementedException('Ivory is currently not capable of inferring the type converter just from the value.');
        // TODO: Implement TypeDictionary::requireTypeByValue()

        $typeName = (is_object($value) ? get_class($value) : gettype($value));
        throw new UndefinedTypeException("There is no type defined for converting value of type \"$typeName\"");
    }

    /**
     * Exports the current state of the dictionary to a PHP class implementing ITypeDictionary.
     */
    public function export()
    {
        throw new \Ivory\Exception\NotImplementedException(); // TODO
    }
}
