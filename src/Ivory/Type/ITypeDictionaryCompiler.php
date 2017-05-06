<?php
namespace Ivory\Type;

interface ITypeDictionaryCompiler
{
    /**
     * Compiles the dictionary based on types and other type-related definitions in the given type providers.
     *
     * @param ITypeProvider $typeProvider type provider to use for getting types and other type-related definitions
     * @return ITypeDictionary
     */
    function compileTypeDictionary(ITypeProvider $typeProvider): ITypeDictionary;
}
