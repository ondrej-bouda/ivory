<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * List of type providers, serving itself as a one big type provider.
 *
 * Type providers may be prepended or appended to the list. {@link ITypeProvider} requests are delegated to the first
 * provider in the list which recognizes the requested object.
 */
class TypeProviderList implements ITypeProvider
{
    /** @var ITypeProvider[] */
    private $typeProviders = [];

    public function appendTypeProvider(ITypeProvider $typeProvider): void
    {
        $this->typeProviders[] = $typeProvider;
    }

    public function prependTypeProvider(ITypeProvider $typeProvider): void
    {
        array_unshift($this->typeProviders, $typeProvider);
    }

    //region ITypeProvider

    public function provideType(string $schemaName, string $typeName): ?IType
    {
        foreach ($this->typeProviders as $typeProvider) {
            $type = $typeProvider->provideType($schemaName, $typeName);
            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    public function getTypeInferenceRules(): array
    {
        $result = [];
        foreach ($this->typeProviders as $typeProvider) {
            $result += $typeProvider->getTypeInferenceRules();
        }
        return $result;
    }

    public function getValueSerializers(): array
    {
        $result = [];
        foreach ($this->typeProviders as $typeProvider) {
            $result += $typeProvider->getValueSerializers();
        }
        return $result;
    }

    public function getTypeAbbreviations(): array
    {
        $result = [];
        foreach ($this->typeProviders as $typeProvider) {
            $result += $typeProvider->getTypeAbbreviations();
        }
        return $result;
    }

    //endregion
}
