<?php
namespace Ivory\Type;

interface ITypeProvider
{
    /**
     * Retrieves the type converter for the specified PostgreSQL type, using the local and the global type register.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the converter for
     * @param string $typeName name of the PostgreSQL type to get the converter for
     * @return IType converter for the requested type, or an {@link \Ivory\Type\UndefinedType} object if no
     *                 corresponding type is registered
     */
    function provideBaseType($schemaName, $typeName);

    /**
     * Provides the implementation of the range canonical function, using the local and the global type register.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the function from
     * @param string $funcName name of the PostgreSQL canonical function to get the implementation for
     * @param ITotallyOrderedType $subtype the range subtype the canonical function should operate on
     * @return IRangeCanonicalFunc|null implementation of the requested canonical function, or <tt>null</tt> if no
     *                                    corresponding implementation is registered
     */
    function provideRangeCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype);
}
