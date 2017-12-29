<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * Responsible for loading type objects, representing PostgreSQL types.
 */
interface ITypeLoader
{
    /**
     * Loads a type object for the specified type.
     *
     * @param string $schemaName name of schema the type is defined in
     * @param string $typeName name of the type; e.g., <tt>"VARCHAR"</tt>
     * @return IType|null the corresponding type object, or <tt>null</tt> if this type loader does not recognize the
     *                      requested type
     */
    function loadType(string $schemaName, string $typeName): ?IType;
}
