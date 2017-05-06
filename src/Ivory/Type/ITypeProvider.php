<?php
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
    function provideType(string $schemaName, string $typeName);

    /**
     * Provides the implementation of the range canonical function.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the function from
     * @param string $funcName name of the PostgreSQL canonical function to get the implementation for
     * @param ITotallyOrderedType $subtype the range subtype the canonical function should operate on
     * @return IRangeCanonicalFunc|null implementation of the requested canonical function, or <tt>null</tt> if no
     *                                    corresponding implementation is registered
     */
    function provideRangeCanonicalFunc(string $schemaName, string $funcName, ITotallyOrderedType $subtype);

    /**
     * Returns the list of all rules for recognizing types from PHP values.
     *
     * @return string[][] map: data type => pair: schema name, type name
     */
    function getTypeRecognitionRules();

    /**
     * @return IValueSerializer[] map: name => value serializer
     */
    function getValueSerializers();

    /**
     * @return string[][] map: abbreviation => pair: schema name, type name
     */
    function getTypeAbbreviations();
}
