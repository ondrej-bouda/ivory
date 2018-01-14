<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Type\Ivory\UndefinedType;
use Ivory\Type\Postgresql\ArrayType;
use Ivory\Type\Postgresql\DomainType;
use Ivory\Type\Postgresql\EnumType;
use Ivory\Type\Postgresql\NamedCompositeType;
use Ivory\Type\Postgresql\RangeType;

abstract class TypeDictionaryCompilerBase implements ITypeDictionaryCompiler
{
    const OPTION_PLAIN_ARRAYS = 1;

    private $options = 0;


    //region Dictionary compilation

    abstract protected function yieldTypes(ITypeProvider $typeProvider, ITypeDictionary $dict): \Generator;

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

        $dict->addTypeInferenceRuleSet($typeProvider->getTypeInferenceRules());

        foreach ($this->yieldTypes($typeProvider, $dict) as $oid => $type) {
            $dict->defineType($type, $oid);
        }

        if ($dict instanceof TrackingTypeDictionary) {
            $dict->resetTypeUsageStats();
        }

        return $dict;
    }

    //endregion

    //region Options

    public function setOption(int $option)
    {
        $this->options = $this->options | $option;
    }

    public function unsetOption(int $option)
    {
        $this->options = $this->options & (~$option);
    }

    public function getOptionsBitmask(): int
    {
        return $this->options;
    }

    //endregion

    //region Type factory methods

    /**
     * Provides the {@link IType} object for the requested type.
     *
     * If the requested type is not recognized by the type provider, an {@link \Ivory\Type\UndefinedType} object is
     * returned.
     *
     * @param string $schemaName
     * @param string $typeName
     * @param ITypeProvider $typeProvider
     * @return IType
     */
    protected function createBaseType(string $schemaName, string $typeName, ITypeProvider $typeProvider): IType
    {
        $type = $typeProvider->provideType($schemaName, $typeName);
        if ($type !== null) {
            return $type;
        } else {
            return new UndefinedType($schemaName, $typeName);
        }
    }

    protected function createCompositeType(string $schemaName, string $typeName): IType
    {
        return new NamedCompositeType($schemaName, $typeName);
    }

    protected function createDomainType(string $schemaName, string $typeName, IType $baseType): IType
    {
        return new DomainType($schemaName, $typeName, $baseType);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @param string[] $labels list of enumeration labels in the definition order
     * @return IType
     */
    protected function createEnumType(string $schemaName, string $typeName, array $labels): IType
    {
        return new EnumType($schemaName, $typeName, $labels);
    }

    protected function createRangeType(string $schemaName, string $typeName, ITotallyOrderedType $subtype): IType
    {
        return new RangeType($schemaName, $typeName, $subtype);
    }

    protected function createArrayType(IType $elemType, string $delimiter): IType
    {
        $type = new ArrayType($elemType, $delimiter);

        if ($this->getOptionsBitmask() & self::OPTION_PLAIN_ARRAYS) {
            $type->switchToPlainMode();
        }

        return $type;
    }

    //endregion
}
