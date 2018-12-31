<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;

class TypeDictionary implements ITypeDictionary
{
    /** @var IType[] */
    protected $oidTypeMap = [];
    /** @var IType[][] map: schema name => map: type name => type object */
    protected $qualNameTypeMap = [];
    /** @var IType[] map: type alias => reference to $this->qualNameTypeMap */
    protected $typeAliases = [];
    /** @var IValueSerializer[] map: serializer name => value serializer */
    protected $valueSerializers = [];
    /** @var string[][][] list: map: PHP data type name => pair (schema name, type name) */
    protected $typeInferenceRuleSets = [];
    /** @var string[] names of schemas to search for a type only given by name without schema */
    private $typeSearchPath = [];
    /** @var ITypeDictionaryUndefinedHandler|null */
    private $undefinedTypeHandler = null;

    /** @var IType[] cache of unqualified type names, preferred according to the current type search path;
     *               map: plain type name => type object (might be a reference to $this->qualNameTypeMap) */
    private $searchedNameCache = [];


    public function __sleep()
    {
        // NOTE: for the subclasses to be able to serialize the dictionary, keep all serialized properties protected
        return [
            'oidTypeMap',
            'qualNameTypeMap',
            'typeAliases',
            'valueSerializers',
            'typeInferenceRuleSets',
            // typeSearchPath and undefinedTypeHandler are expected to be set again after unserializing.
            // searchedNameCache is needless to serialize - it must be recomputed anyway when the type search path is set.
        ];
    }

    public function __construct()
    {
    }

    //region ITypeDictionary

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
            if (isset($this->typeAliases[$typeName])) {
                return $this->typeAliases[$typeName];
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
        foreach ($this->typeInferenceRuleSets as $ruleSet) {
            if ($ruleSet) {
                $rule = $this->inferType($value, $ruleSet);
                if ($rule) {
                    return $this->requireTypeByName($rule[1], $rule[0]);
                }
            }
        }

        $typeName = (is_object($value) ? get_class($value) : gettype($value));
        throw new UndefinedTypeException("There is no type defined for converting value of type \"$typeName\"");
    }

    private function inferType($value, $ruleSet): ?array
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
            return $this->inferObjectType($value, $ruleSet);
        } elseif (is_array($value)) {
            return $this->inferArrayType($value, $ruleSet);
        } else {
            throw new \InvalidArgumentException('$value');
        }
    }

    private function inferObjectType($value, $ruleSet): ?array
    {
        try {
            $valueClass = new \ReflectionClass($value);
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException('$value is neither an object nor a classname');
        }

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

    private function inferArrayType($value, $ruleSet): ?array
    {
        $element = $this->findFirstSignificantElement($value);

        if ($element !== null) {
            $elmtType = $this->inferType($element, $ruleSet);
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

    public function getValueSerializer(string $name): ?IValueSerializer
    {
        return ($this->valueSerializers[$name] ?? null);
    }

    public function getUndefinedTypeHandler(): ?ITypeDictionaryUndefinedHandler
    {
        return $this->undefinedTypeHandler;
    }

    public function setUndefinedTypeHandler(?ITypeDictionaryUndefinedHandler $undefinedTypeHandler): void
    {
        $this->undefinedTypeHandler = $undefinedTypeHandler;
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
    public function setTypeSearchPath(array $schemaList): void
    {
        if ($this->typeSearchPath == $schemaList) {
            return;
        }

        $this->typeSearchPath = $schemaList;
        $this->recomputeNameCache();
    }

    public function attachToConnection(IConnection $connection): void
    {
        $connDepTypes = $this->collectObjects(IConnectionDependentObject::class);
        foreach ($connDepTypes as $type) {
            assert($type instanceof IConnectionDependentObject);
            $type->attachToConnection($connection);
        }
    }

    public function detachFromConnection(): void
    {
        $connDepTypes = $this->collectObjects(IConnectionDependentObject::class);
        foreach ($connDepTypes as $type) {
            assert($type instanceof IConnectionDependentObject);
            $type->detachFromConnection();
        }
    }

    //endregion

    //region content management

    public function defineType(IType $type, ?int $oid = null): void
    {
        if ($oid !== null) {
            $this->oidTypeMap[$oid] = $type;
        }

        $schemaName = $type->getSchemaName();
        $typeName = $type->getName();

        if (!isset($this->qualNameTypeMap[$schemaName])) {
            $this->qualNameTypeMap[$schemaName] = [];
        }
        $this->qualNameTypeMap[$schemaName][$typeName] = $type;

        $this->cacheType($type);
    }

    private function cacheType(IType $type): void
    {
        $schemaName = $type->getSchemaName();
        $typeName = $type->getName();

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

    /**
     * Removes the type from the dictionary.
     *
     * @param IType $type
     */
    public function disposeType(IType $type): void
    {
        $schema = $type->getSchemaName();
        $name = $type->getName();

        unset($this->qualNameTypeMap[$schema][$name]);

        self::removeAll($this->typeAliases, $type);
        self::removeAll($this->oidTypeMap, $type);

        if (($this->searchedNameCache[$name] ?? null) === $type) {
            unset($this->searchedNameCache[$name]);
        }

        self::removeAll($this->valueSerializers, $type);
    }

    private static function removeAll(array &$array, $item): void
    {
        while (($key = array_search($item, $array, true)) !== false) {
            unset($array[$key]);
        }
    }

    public function defineValueSerializer(string $name, IValueSerializer $valueSerializer): void
    {
        $this->valueSerializers[$name] = $valueSerializer;
    }

    public function defineTypeAlias(string $alias, string $schemaName, string $typeName): void
    {
        if (!isset($this->qualNameTypeMap[$schemaName])) {
            // necessary for the forward reference to be valid once the aliased type finally appears in the type map
            $this->qualNameTypeMap[$schemaName] = [];
        }
        $this->typeAliases[$alias] =& $this->qualNameTypeMap[$schemaName][$typeName];
    }

    /**
     * @param string[][] $ruleSet map: PHP data type name => pair: (schema name, type name)
     */
    public function addTypeInferenceRuleSet(array $ruleSet): void
    {
        $this->typeInferenceRuleSets[] = $ruleSet;
    }

    private function recomputeNameCache(): void
    {
        $this->searchedNameCache = [];
        foreach ($this->typeSearchPath as $schemaName) {
            foreach (($this->qualNameTypeMap[$schemaName] ?? []) as $typeName => $type) {
                if (!isset($this->searchedNameCache[$typeName])) {
                    $this->searchedNameCache[$typeName] =& $this->qualNameTypeMap[$schemaName][$typeName];
                }
            }
        }
    }

    protected function collectObjects(string $className): \SplObjectStorage
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
        foreach ($this->valueSerializers as $serializer) {
            if ($serializer instanceof $className) {
                $result->attach($serializer);
            }
        }

        return $result;
    }

    //endregion
}
