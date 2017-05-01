<?php
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;
use Ivory\Type\IConnectionDependentObject;

/**
 * Pattern type converter base the operation of which depends on the database connection.
 */
abstract class VolatilePatternTypeBase extends PatternTypeBase implements IConnectionDependentObject
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
