<?php
declare(strict_types=1);
namespace Ivory\Type;

interface ITypeDictionaryUndefinedHandler
{
    /**
     * Handles an unsatisfied request for retrieving a type from a {@link TypeDictionary}.
     *
     * The handler is given either OID, (schema name, type name) pair, or value by which the type was being searched but
     * not found. If an adequate {@link IType} object is known to the handler, it may be returned to save the day.
     * Otherwise, `null` is to be returned.
     *
     * @param int|null $oid
     * @param string|bool|null $schemaName name of schema the type is defined in;
     *                                <tt>null</tt> to get the type by its unqualified name (either it is an alias or
     *                                  the search path is to be used to find the first type by its unqualified name);
     *                                <tt>false</tt> to only use the search path to figure out the schema name
     * @param string|null $typeName name of type to get
     * @param mixed $value
     * @return IType|null
     */
    function handleUndefinedType(?int $oid, $schemaName, ?string $typeName, $value): ?IType;
}
