<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

interface ITypeDictionary
{
    /**
     * Searches the dictionary for a type converter according to the given type OID.
     *
     * @param int $oid
     * @return IType
     * @throws UndefinedTypeException if no type of the given OID is found
     */
    function requireTypeByOid($oid): IType;

    /**
     * Searches the dictionary for a type converter according to the given type name or abbreviation.
     *
     * @param string $typeName name of type to get the converter for
     * @param string|bool $schemaName name of schema the type is defined in;
     *                                <tt>null</tt> to get the type by its unqualified name (either it is a custom type
     *                                  or alias, or the search path is to be used to find the first type);
     *                                <tt>false</tt> to only use the search path to figure out the schema name
     * @return IType
     * @throws UndefinedTypeException if no type of the given name is found
     */
    function requireTypeByName(string $typeName, $schemaName = null): IType;

    /**
     * Searches the dictionary for a type converter the most suitable for converting the given value.
     *
     * @param mixed $value
     * @return IType
     * @throws UndefinedTypeException if no type capable of converting the given value is found
     */
    function requireTypeByValue($value): IType;
}
