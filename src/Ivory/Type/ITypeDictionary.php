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
    function requireTypeByOid($oid) : IType;

    /**
     * Searches the dictionary for a type converter according to the given type name or abbreviation.
     *
     * @param string $name
     * @return IType
     * @throws UndefinedTypeException if no type of the given name is found
     */
    function requireTypeByName($name) : IType;

    /**
     * Searches the dictionary for a type converter the most suitable for converting the given value.
     *
     * @param mixed $value
     * @return IType
     * @throws UndefinedTypeException if no type capable of converting the given value is found
     */
    function requireTypeByValue($value) : IType;
}
