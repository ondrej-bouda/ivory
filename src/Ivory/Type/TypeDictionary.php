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
    /** @var INamedType[][] map: schema name => map: type name => type converter */
    private $qualNameTypeMap = [];
    /** @var INamedType[] map: type name => type converter (might be a reference to $this->qualNameTypeMap) */
    private $nameTypeMap = [];
    /** @var string[] names of schemas to search for a type only given by name without schema */
    private $typeSearchPath = [];
    /** @var ITypeDictionaryUndefinedHandler|null */
    private $undefinedTypeHandler = null;

    /** @var INamedType[] cache of unqualified type names, preferred according to the current type search path;
     *                    map: plain type name => type converter (might be a reference to $this->qualNameTypeMap) */
    private $searchedNameCache = [];


    public function defineType($oid, IType $type)
    {
        $this->oidTypeMap[$oid] = $type;

        if ($type instanceof INamedType) {
            $schemaName = $type->getSchemaName();
            $typeName = $type->getName();

            if (!isset($this->qualNameTypeMap[$schemaName])) {
                $this->qualNameTypeMap[$schemaName] = [];
            }
            $this->qualNameTypeMap[$schemaName][$typeName] = $type;

            // cache
            $searchPathPos = array_search($schemaName, $this->typeSearchPath);
            if ($searchPathPos !== false) {
                if (!isset($this->searchedNameCache[$typeName])) {
                    $this->searchedNameCache[$typeName] = $type;
                } else {
                    // find out whether the schema of $type is more preferred
                    $cachedPos = array_search(
                        $this->searchedNameCache[$typeName]->getSchemaName(),
                        $this->typeSearchPath
                    );
                    if ($cachedPos === false || $cachedPos > $searchPathPos) {
                        $this->searchedNameCache[$typeName] = $type;
                    }
                }
            }
        }
    }

    public function defineCustomType(string $name, IType $type)
    {
        $this->nameTypeMap[$name] = $type;
    }

    public function defineTypeAlias(string $alias, string $schemaName, string $typeName)
    {
        $this->nameTypeMap[$alias] =& $this->qualNameTypeMap[$schemaName][$typeName];
    }

    /**
     * @return string[] schema name list
     */
    public function getTypeSearchPath(): array
    {
        return $this->typeSearchPath;
    }

    /**
     * @param string[] $schemaList
     */
    public function setTypeSearchPath(array $schemaList)
    {
        if ($this->typeSearchPath == $schemaList) {
            return;
        }

        $this->typeSearchPath = $schemaList;
        $this->recomputeNameCache();
    }

    private function recomputeNameCache()
    {
        $this->searchedNameCache = [];
        foreach ($this->typeSearchPath as $schemaName) {
            foreach (($this->qualNameTypeMap[$schemaName] ?? []) as $typeName => $converter) {
                if (!isset($this->searchedNameCache[$typeName])) {
                    $this->searchedNameCache[$typeName] =& $this->qualNameTypeMap[$schemaName][$typeName];
                }
            }
        }
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

    public function requireTypeByOid($oid): IType
    {
        if (isset($this->oidTypeMap[$oid])) {
            return $this->oidTypeMap[$oid];
        }

        if ($this->undefinedTypeHandler !== null) {
            $type = $this->undefinedTypeHandler->handleUndefinedType($oid, null, null, null);
            if ($type !== null) {
                return $type;
            }
        }

        throw new UndefinedTypeException("There is no type defined for OID $oid");
    }

    public function requireTypeByName(string $typeName, $schemaName = null): IType
    {
        if ($schemaName === null) {
            if (isset($this->nameTypeMap[$typeName])) {
                return $this->nameTypeMap[$typeName];
            }
            if (isset($this->searchedNameCache[$typeName])) {
                return $this->searchedNameCache[$typeName];
            }
        } elseif ($schemaName === false) {
            if (isset($this->searchedNameCache[$typeName])) {
                return $this->searchedNameCache[$typeName];
            }
        } else {
            if (isset($this->qualNameTypeMap[$schemaName][$typeName])) {
                return $this->qualNameTypeMap[$schemaName][$typeName];
            }
        }

        if ($this->undefinedTypeHandler !== null) {
            $type = $this->undefinedTypeHandler->handleUndefinedType(null, $schemaName, $typeName, null);
            if ($type !== null) {
                return $type;
            }
        }

        $msg = "There is no type defined for name \"$typeName\"";
        if ($schemaName !== null && $schemaName !== false) {
            $msg .= " in schema \"$schemaName\"";
        }
        throw new UndefinedTypeException($msg);
    }

    public function requireTypeByValue($value): IType
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
