<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

/**
 * An object which depends on a concrete connection in some way.
 *
 * Users of any object implementing `IConnectionDependentObject` are obliged to:
 * - call the {@link attachToConnection()} prior to any usage, and call it in case of further changes of the connection;
 * - call the {@link detachFromConnection()} prior to serializing or exporting the implementing object.
 */
interface IConnectionDependentObject
{
    /**
     * Attach the object to connection it is supposed to work for.
     *
     * This method must be called prior to any usage of the this object.
     *
     * @param IConnection $connection
     */
    function attachToConnection(IConnection $connection);

    /**
     * Detach the object from the connection.
     *
     * After executing this method, the object must be completely independent from the connection. Especially, no links
     * to objects retrieved from the attached connection may be kept.
     *
     * Prior to further usage of this object, {@link attachToConnection()} must be called again.
     */
    function detachFromConnection();
}
