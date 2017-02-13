<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

/**
 * Responsible for loading type objects, representing PostgreSQL types.
 */
interface ITypeLoader
{
    /**
     * Loads a type converter object for the specified type.
     *
     * @param string $schemaName name of schema the type is defined in
     * @param string $typeName name of the type; e.g., <tt>"VARCHAR"</tt>
     * @param IConnection $connection connection above which the type is to be loaded;
     *                                this might be necessary for some special data types which need the database
     *                                  session parameters, such as <tt>money</tt>
     * @return IType|null the corresponding type object, or
     *                    <tt>null</tt> if this type loader does not recognize the given type
     */
    function loadType($schemaName, $typeName, IConnection $connection);
}
