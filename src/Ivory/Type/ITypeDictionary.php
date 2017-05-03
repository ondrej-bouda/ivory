<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

interface ITypeDictionary
{
    /**
     * Searches the dictionary for a type according to the given type OID.
     *
     * @param int $oid
     * @return IType
     * @throws UndefinedTypeException if no type of the given OID is found
     */
    function requireTypeByOid(int $oid): IType;

    /**
     * Searches the dictionary for a type according to the given type name or abbreviation.
     *
     * @param string $typeName name of type to get
     * @param string|bool $schemaName name of schema the type is defined in;
     *                                <tt>null</tt> to get the type by its unqualified name (either it is an alias or
     *                                  the search path is to be used to find the first type by its unqualified name);
     *                                <tt>false</tt> to only use the search path to figure out the schema name
     * @return IType
     * @throws UndefinedTypeException if no type of the given name is found
     */
    function requireTypeByName(string $typeName, $schemaName = null): IType;

    /**
     * Searches the dictionary for the most suitable type for serializing the given value to PostgreSQL.
     *
     * @param mixed $value
     * @return IType
     * @throws UndefinedTypeException if no type capable of converting the given value is found
     */
    function requireTypeByValue($value): IType;

    /**
     * Searches the dictionary for a value serializer of the given name.
     *
     * @param string $name
     * @return IValueSerializer|null value serializer of the given name, or <tt>null</tt> if no such serializer is defined
     */
    function getValueSerializer(string $name);

    /**
     * @param ITypeDictionaryUndefinedHandler|null $undefinedTypeHandler
     */
    function setUndefinedTypeHandler($undefinedTypeHandler);

    /**
     * @param string[] $schemaList
     */
    function setTypeSearchPath(array $schemaList);
}
