<?php
declare(strict_types=1);

namespace Ivory\Type;

abstract class TypeDictionaryCompilerBase implements ITypeDictionaryCompiler
{
    protected function createTypeDictionary(): TypeDictionary
    {
        return new TrackingTypeDictionary();
    }

    public function compileTypeDictionary(ITypeProvider $typeProvider): ITypeDictionary
    {
        $dict = $this->createTypeDictionary();

        foreach ($typeProvider->getTypeAbbreviations() as $abbr => list($schemaName, $typeName)) {
            $dict->defineTypeAlias($abbr, $schemaName, $typeName);
            $dict->defineTypeAlias("{$abbr}[]", $schemaName, "{$typeName}[]");
        }

        foreach ($typeProvider->getValueSerializers() as $name => $type) {
            $dict->defineValueSerializer($name, $type);
        }

        $dict->addTypeRecognitionRuleSet($typeProvider->getTypeRecognitionRules());

        foreach ($this->yieldTypes($typeProvider, $dict) as $oid => $type) {
            $dict->defineType($type, $oid);
        }

        if ($dict instanceof TrackingTypeDictionary) {
            $dict->resetTypeUsageStats();
        }

        return $dict;
    }

    abstract protected function yieldTypes(ITypeProvider $typeProvider, ITypeDictionary $dict): \Generator;
}
