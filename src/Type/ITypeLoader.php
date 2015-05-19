<?php
namespace Ivory\Type;

/**
 * Responsible for loading type objects, representing PostgreSQL types.
 */
interface ITypeLoader
{
	/**
	 * Loads a data type object for the specified type.
	 *
	 * Note the returned type object will be cached by the {@link TypeRegister} and reused upon further calls for this
	 * type.
	 *
	 * @param string $typeName name of the type; e.g., <tt>"VARCHAR"</tt>
	 * @param string $schemaName name of schema the type is defined in
	 * @param \Ivory\IConnection $connection connection above which the type is to be loaded
	 * @return IType|null the corresponding type object, or
	 *                    <tt>null</tt> if this type loader does not recognize the given type
	 */
	function loadType($typeName, $schemaName, \Ivory\IConnection $connection);
}
