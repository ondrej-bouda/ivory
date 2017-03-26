<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

/**
 * {@inheritdoc}
 *
 * Users of any type converter implementing `IConnectionDependentType` are obliged to:
 * - call the {@link attachToConnection()} prior to any usage, and call it in case of further changes of the connection;
 * - call the {@link detachFromConnection()} prior to serializing or exporting the implementing type converter.
 */
interface IConnectionDependentType extends IType
{
    /**
     * Attach the type converter to the connection it is supposed to work for.
     *
     * This method must be called prior to any usage of the this type converter.
     *
     * @param IConnection $connection
     */
    function attachToConnection(IConnection $connection);

    /**
     * Detach the type converter from the connection.
     *
     * After executing this method, the type converter must be safe to be serialized or exported. Especially, no links
     * to objects retrieved from the attached connection may be kept.
     *
     * Prior to further usage of this type converter, {@link attachToConnection()} must be called again.
     */
    function detachFromConnection();
}
