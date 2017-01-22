<?php
namespace Ivory\Type;

interface ITypeDictionaryUndefinedHandler
{
    /**
     * Handles an unsatisfied request for retrieving a type converter from a {@link TypeDictionary}.
     *
     * The handler is given either OID, name or value by which the type was being searched but not found. If an adequate
     * {@link IType} object is known to the handler, it may be returned to save the day. Otherwise, `null` is to be
     * returned.
     *
     * @param int|null $oid
     * @param string|null $name
     * @param mixed $value
     * @return IType|null
     */
    function handleUndefinedType($oid, $name, $value);
}
