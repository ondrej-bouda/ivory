<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;

/**
 * Dictionary of data types on a concrete database.
 */
class TypeDictionary implements ICacheableTypeDictionary
{
    /** @var IType[] */
    private $oidTypeMap = [];
    /** @var INamedType[][] map: schema name => map: type name => type converter */
    private $qualNameTypeMap = [];
    /** @var INamedType[] map: type name => type converter (might be a reference to $this->qualNameTypeMap) */
    private $nameTypeMap = [];
    /** @var string[][][] list: map: PHP data type name => pair (schema name, type name) */
    private $typeRecognitionRuleSets = [];
    /** @var string[] names of schemas to search for a type only given by name without schema */
    private $typeSearchPath = [];
    /** @var ITypeDictionaryUndefinedHandler|null */
    private $undefinedTypeHandler = null;

    /** @var INamedType[] cache of unqualified type names, preferred according to the current type search path;
     *                    map: plain type name => type converter (might be a reference to $this->qualNameTypeMap) */
    private $searchedNameCache = [];


    public function defineType(IType $type, int $oid = null)
    {
        if ($oid !== null) {
            $this->oidTypeMap[$oid] = $type;
        }

        if ($type instanceof INamedType) {
            $schemaName = $type->getSchemaName();
            $typeName = $type->getName();

            if (!isset($this->qualNameTypeMap[$schemaName])) {
                $this->qualNameTypeMap[$schemaName] = [];
            }
            $this->qualNameTypeMap[$schemaName][$typeName] = $type;

            $this->cacheNamedType($schemaName, $typeName, $type);
        }
    }

    private function cacheNamedType(string $schemaName, string $typeName, IType $type)
    {
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

    /**
     * @param string[][] $ruleSet map: PHP data type name => pair: (schema name, type name)
     */
    public function addTypeRecognitionRuleSet(array $ruleSet)
    {
        array_unshift($this->typeRecognitionRuleSets, $ruleSet);
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

    public function requireTypeByOid(int $oid): IType
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
        foreach ($this->typeRecognitionRuleSets as $ruleSet) {
            if ($ruleSet) {
                $rule = $this->recognizeType($value, $ruleSet);
                if ($rule) {
                    return $this->requireTypeByName($rule[1], $rule[0]);
                }
            }
        }

        $typeName = (is_object($value) ? get_class($value) : gettype($value));
        throw new UndefinedTypeException("There is no type defined for converting value of type \"$typeName\"");
    }

    private function recognizeType($value, $ruleSet)
    {
        static $gettypeMap = [
            'boolean' => 'bool',
            'integer' => 'int',
            'double' => 'float',
            'string' => 'string',
            'NULL' => 'null',
        ];
        $type = gettype($value);
        if (isset($gettypeMap[$type])) {
            $ruleName = $gettypeMap[$type];
            if (isset($ruleSet[$ruleName])) {
                return $ruleSet[$ruleName];
            } elseif ($ruleName == 'int' && isset($ruleSet['float'])) {
                return $ruleSet['float'];
            } else {
                return null;
            }
        } elseif (is_object($value)) {
            return $this->recognizeObjectType($value, $ruleSet);
        } elseif (is_array($value)) {
            return $this->recognizeArrayType($value, $ruleSet);
        } else {
            throw new \InvalidArgumentException('$value');
        }
    }

    private function recognizeObjectType($value, $ruleSet)
    {
        $valueClass = new \ReflectionClass($value);
        $class = $valueClass;
        do {
            $name = $class->getName();
            if (isset($ruleSet[$name])) {
                return $ruleSet[$name];
            }
            $class = $class->getParentClass();
        } while ($class);

        foreach ($valueClass->getInterfaceNames() as $name) {
            if (isset($ruleSet[$name])) {
                return $ruleSet[$name];
            }
        }
        return null;
    }

    private function recognizeArrayType($value, $ruleSet)
    {
        $element = $this->findFirstSignificantElement($value);

        if ($element !== null) {
            $elmtType = $this->recognizeType($element, $ruleSet);
            if ($elmtType !== null) {
                return [$elmtType[0], $elmtType[1] . '[]'];
            } else {
                return null;
            }
        } else {
            return ($ruleSet['array'] ?? null);
        }
    }

    private function findFirstSignificantElement(array $value)
    {
        foreach ($value as $element) {
            if (is_array($element)) {
                $element = $this->findFirstSignificantElement($element);
            }
            if ($element !== null) {
                return $element;
            }
        }
        return null;
    }

    public function detachFromConnection()
    {
        $connDepTypes = $this->collectTypes(IConnectionDependentType::class);
        foreach ($connDepTypes as $type) {
            /** @var IConnectionDependentType $type */
            $type->detachFromConnection();
        }
    }

    public function attachToConnection(IConnection $connection)
    {
        $connDepTypes = $this->collectTypes(IConnectionDependentType::class);
        foreach ($connDepTypes as $type) {
            /** @var IConnectionDependentType $type */
            $type->attachToConnection($connection);
        }
    }

    private function collectTypes(string $className): \SplObjectStorage
    {
        $result = new \SplObjectStorage();
        foreach ($this->oidTypeMap as $type) {
            if ($type instanceof $className) {
                $result->attach($type);
            }
        }
        foreach ($this->qualNameTypeMap as $types) {
            foreach ($types as $type) {
                if ($type instanceof $className) {
                    $result->attach($type);
                }
            }
        }
        foreach ($this->nameTypeMap as $type) {
            if ($type instanceof $className) {
                $result->attach($type);
            }
        }

        return $result;
    }
}
