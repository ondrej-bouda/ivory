<?php
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;

/**
 * Pattern type converter base the operation of which depends on the database connection.
 */
abstract class VolatilePatternTypeBase extends PatternTypeBase
{
    private $conn;

    public function __construct(IConnection $connection)
    {
        $this->conn = $connection;
    }

    protected function getConnection() : IConnection
    {
        return $this->conn;
    }
}
