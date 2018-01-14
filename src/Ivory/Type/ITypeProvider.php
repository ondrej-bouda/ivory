<?php
declare(strict_types=1);
namespace Ivory\Type;

interface ITypeProvider
{
    /**
     * Retrieves the type object for the specified PostgreSQL type, if it is known to the type provider.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the type object for
     * @param string $typeName name of the PostgreSQL type to get the type object for
     * @return IType|null the requested type object, or <tt>null</tt> if no corresponding type object is registered
     */
    function provideType(string $schemaName, string $typeName): ?IType;

    /**
     * Returns the list of all rules for inferring types from PHP values.
     *
     * @return string[][] map: PHP data type => pair: schema name, type name
     */
    function getTypeInferenceRules(): array;

    /**
     * @return IValueSerializer[] map: name => value serializer
     */
    function getValueSerializers(): array;

    /**
     * @return string[][] map: abbreviation => pair: schema name, type name
     */
    function getTypeAbbreviations(): array;
}
