<?php
namespace Ivory\Type;

use Ivory\Connection;

/**
 * Manages the correspondence between PostgreSQL types and PHP types.
 *
 * The types are managed per-connection, as types of same names might exist in different databases Ivory is
 * simultaneously connected to. Besides connection-specific types, there are global types, which get used for any
 * connection for which there is no overriding connection-specific type.
 */
class TypeRegister
{
	use \Ivory\Utils\Singleton; // TODO: consider implementing the singleton using static methods to be consistent with the Ivory class


	/** @var IType[][][] map: connection name => map: schema name => map: type name => type object */
	private $types;

	/** @var ITypeLoader[][] map: connection name => list: registered type loaders, in the definition order */
	private $typeLoaders;


	/**
	 * Returns a type object corresponding to the requested PostgreSQL type.
	 *
	 * @param string $typeName name of the type; e.g., <tt>"VARCHAR"</tt>
	 * @param string $schemeName name of schema the type is defined in
	 * @param Connection|string|null $connection (name of) connection for which to retrieve the type object;
	 *                                  <tt>null</tt> to only search within global types
	 * @return IType the requested type object
	 * @throws \Ivory\UndefinedTypeException if no corresponding type is defined
	 */
	public function getType($typeName, $schemeName, $connection)
	{

	}

	/**
	 * Finds out whether Returns a type object corresponding to the requested PostgreSQL type.
	 *
	 * @param string $typeName name of the type; e.g., <tt>"VARCHAR"</tt>
	 * @param string $schemeName name of schema the type is defined in
	 * @param Connection|string|null $connection (name of) connection for which to retrieve the type object;
	 *                                  <tt>null</tt> to only search within global types
	 * @return IType the requested type object
	 * @throws \Ivory\UndefinedTypeException if no corresponding type is defined
	 */
	public function hasType($typeName, $schemeName, $connection)
	{

	}

	/**
	 * Registers a data type object for representing a PostgreSQL data type.
	 *
	 * If a data type object has already been registered, it gets overwritten with the
	 *
	 * @param INamedType $type the type object to register
	 * @param Connection|string|null $connection (name of) the connection to register the data type object for;
	 *                                  if <tt>null</tt>, the type is registered globally, i.e., will be used for all
	 *                                    connections which do not themselves define a type of the same name
	 */
	public function registerType(INamedType $type, $connection = null)
	{

	}

	/**
	 * Unregisters a data type object, previously registered by {@link registerType()} or loaded by a data type loader.
	 *
	 * @param INamedType|string $typeOrName (name of) the type to unregister
	 * @param string|null $schemaName name of schema the type is defined in - relevant only if <tt>$typeOrName</tt> is a
	 *                                  <tt>string</tt> (and thus contains a name); skip with <tt>null</tt>
	 * @param Connection|string|null $connection (name of) connection to unregister the data type object for;
	 *                                  if <tt>null</tt>, a global registered type is unregistered
	 * @return bool whether the type has actually been unregistered (<tt>false</tt> if it was not registered)
	 */
	public function unregisterType($typeOrName, $schemaName = null, $connection = null)
	{

	}

	/**
	 * Registers a data type loader.
	 *
	 * @param ITypeLoader $typeLoader type loader to register
	 * @param Connection|string|null $connection (name of) the connection to register the data type loader for;
	 *                                  if <tt>null</tt>, the type loader is registered globally, i.e., will be used for
	 *                                    all connections;
	 *                                  when loading a type object, the connection-specific type loaders are tried in
	 *                                    the same order as they were registered, then the global type loaders are tried
	 *                                    (again, in the registration order);
	 */
	public function registerTypeLoader(ITypeLoader $typeLoader, $connection = null)
	{

	}

	/**
	 * Unregisters a previously registered data type loader.
	 *
	 * @param ITypeLoader $typeLoader type loader to unregister
	 * @param Connection|string|null $connection (name of) the connection to unregister the data type loader for;
	 *                                  <tt>null</tt> to unregister a global data type loader
	 * @return bool whether the type loader has actually been unregistered (<tt>false</tt> if it was not registered)
	 */
	public function unregisterTypeLoader(ITypeLoader $typeLoader, $connection = null)
	{

	}

	/**
	 * Retrieves all the registered type loaders for a given connection, or the global ones.
	 *
	 * @param Connection|string|null $connection (name of) the connection to get the data type loaders for;
	 *                                  <tt>null</tt> to get the global data type loaders
	 * @return ITypeLoader[] the list of the registered data type loaders, in the registration order
	 */
	public function getTypeLoaders($connection = null)
	{

	}


	// TODO: standard type loaders:
	//       - interface-based: recognizes all types implementing a specific marking interface;
	//                          especially, all types generated by the type generator are tagged by this interface
	//       - built-in types: recognize all built-in types; might actually be tagged by the same interface as mentioned
	//                         above
	//       - introspector: generates the type on-the-fly by querying the database for the type metadata;
	//                       this might serve the ones who do not use the type generator, or as the last resort;
	//                       some caching shall be provided
	// TODO: support type aliases
	// TODO: allow to cover multiple PostgreSQL types by a single Ivory type;
	//       remember the PostgreSQL type, though; that leads to type constructors


	// define mapping for native types
	// define mapping built-in types, recognized in the standard Postgres database
	// recognize the structured types associated with database tables; thus, process e.g. attributes of type "person[]"
	// also recognize anonymous RECORDs, e.g., from query:   WITH a (k,l,m) AS (VALUES (1, 'a', true)) SELECT a FROM a
	// allow the user to define their own types, or even redefine the above ones
}
