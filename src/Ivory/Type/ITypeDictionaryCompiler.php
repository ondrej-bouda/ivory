<?php
namespace Ivory\Type;

interface ITypeDictionaryCompiler
{
    function compileTypeDictionary(ITypeProvider $typeProvider): ITypeDictionary;
}
