<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * {@link ITypeDictionary Type dictionary} which keeps stats on types usage.
 */
class TrackingTypeDictionary extends TypeDictionary
{
    private $watchTypeUsage = true;
    private $usedTypes;


    public function __construct()
    {
        parent::__construct();

        $this->usedTypes = new \SplObjectStorage();
    }

    /**
     * Marks all types in this dictionary, which are also contained in the given dictionary, as used.
     *
     * The types in the dictionaries are compared by their fully-qualified names and PHP classes.
     *
     * @param TypeDictionary $typeDictionary
     */
    public function markAllDictionaryTypesAsUsed(TypeDictionary $typeDictionary): void
    {
        $typeClasses = [];
        foreach ($typeDictionary->collectObjects(IType::class) as $type) {
            assert($type instanceof IType);
            $schema = $type->getSchemaName();
            $name = $type->getName();

            if (!isset($typeClasses[$schema])) {
                $typeClasses[$schema] = [];
            }
            $typeClasses[$schema][$name] = get_class($type);
        }

        foreach ($this->collectObjects(IType::class) as $localType) {
            assert($localType instanceof IType);
            $remoteClass = ($typeClasses[$localType->getSchemaName()][$localType->getName()] ?? null);

            if ($remoteClass == get_class($localType)) {
                $this->markTypeUsage($localType);
            }
        }
    }

    private function markTypeUsage(IType $type): void
    {
        $this->usedTypes->attach($type);
    }

    public function enableTypeUsageWatching(): void
    {
        $this->watchTypeUsage = true;
    }

    public function disableTypeUsageWatching(): void
    {
        $this->watchTypeUsage = false;
    }

    public function resetTypeUsageStats(): void
    {
        $this->usedTypes = new \SplObjectStorage();
    }

    public function disposeUnusedTypes(): void
    {
        foreach ($this->collectObjects(IType::class) as $type) {
            assert($type instanceof IType);
            if (!$this->usedTypes->contains($type)) {
                $this->disposeType($type);
            }
        }
    }


    public function requireTypeByOid(int $oid): IType
    {
        $type = parent::requireTypeByOid($oid);
        if ($this->watchTypeUsage) {
            $this->markTypeUsage($type);
        }
        return $type;
    }

    public function requireTypeByName(string $typeName, $schemaName = null): IType
    {
        $type = parent::requireTypeByName($typeName, $schemaName);
        if ($this->watchTypeUsage) {
            $this->markTypeUsage($type);
        }
        return $type;
    }

    public function requireTypeByValue($value): IType
    {
        $type = parent::requireTypeByValue($value);
        if ($this->watchTypeUsage) {
            $this->markTypeUsage($type);
        }
        return $type;
    }

    public function getValueSerializer(string $name): ?IValueSerializer
    {
        $serializer = parent::getValueSerializer($name);
        if ($this->watchTypeUsage && $serializer instanceof IType) {
            $this->markTypeUsage($serializer);
        }
        return $serializer;
    }
}
