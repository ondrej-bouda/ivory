<?php
namespace Ivory\Type;

interface ITypeProvider
{
    /**
     * Retrieves the type converter for the specified PostgreSQL type, using the local and the global type register.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the converter for
     * @param string $typeName name of the PostgreSQL type to get the converter for
     * @return IType converter for the requested type, or an {@link UndefinedType} object if no corresponding type is
     *                 registered
     */
    function provideBaseType($schemaName, $typeName);
}
