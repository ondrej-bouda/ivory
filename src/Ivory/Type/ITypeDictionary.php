<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;

/**
 * Dictionary of all the specific types and other type-related objects and definitions prepared for being used.
 *
 * A type dictionary is the interface of the type system to other Ivory subsystems. All the definitions and rules are
 * compiled in the dictionary, so that just the dictionary is needed for serializing, parsing and inferring types.
 */
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
     * @return IValueSerializer|null value serializer of the given name, or <tt>null</tt> if no such serializer is
     *                                 defined
     */
    function getValueSerializer(string $name): ?IValueSerializer;

    /**
     * Defines the handler consulted when a data type is required but not found.
     *
     * @param ITypeDictionaryUndefinedHandler|null $undefinedTypeHandler
     */
    function setUndefinedTypeHandler(?ITypeDictionaryUndefinedHandler $undefinedTypeHandler): void;

    /**
     * Sets the search path for looking up data types by their unqualified names.
     *
     * The mechanism is equivalent to the PostgreSQL `search_path` facility: a list of schema names is given. Then, an
     * unqualified type name is consecutively searched in schemas from the list. It is used mainly for serializing SQL
     * pattern arguments.
     *
     * @param string[] $schemaList
     */
    function setTypeSearchPath(array $schemaList): void;

    /**
     * Attach connection-dependent objects in this dictionary to a connection.
     *
     * @param IConnection $connection connection to attach the dictionary to
     */
    function attachToConnection(IConnection $connection): void;

    /**
     * Detach any objects in this dictionary from the database connection.
     *
     * After this operation, the dictionary must be safe for serialization.
     */
    function detachFromConnection(): void;
}
