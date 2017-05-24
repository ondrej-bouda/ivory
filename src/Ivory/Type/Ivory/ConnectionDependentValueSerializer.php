<?php
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;
use Ivory\Type\IConnectionDependentObject;
use Ivory\Type\IValueSerializer;

/**
 * A value serializer the operation of which depends on the database connection.
 */
abstract class ConnectionDependentValueSerializer implements IValueSerializer, IConnectionDependentObject
{
    private $conn;

    public function __construct(IConnection $connection = null)
    {
        if ($connection !== null) {
            $this->attachToConnection($connection);
        }
    }

    public function attachToConnection(IConnection $connection)
    {
        $this->conn = $connection;
    }

    public function detachFromConnection()
    {
        $this->conn = null;
    }

    protected function getConnection(): IConnection
    {
        return $this->conn;
    }
}