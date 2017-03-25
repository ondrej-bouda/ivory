<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

interface ICacheableTypeDictionary extends ITypeDictionary
{
    /**
     * Detach any objects in this dictionary from the database connection.
     *
     * After this operation, the dictionary must be safe for serialization.
     */
    function detachFromConnection();

    /**
     * Attach connection-dependent objects in this dictionary to a connection.
     *
     * @param IConnection $connection connection to attach the dictionary to
     */
    function attachToConnection(IConnection $connection);
}
