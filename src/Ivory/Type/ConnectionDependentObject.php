<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\InvalidStateException;

trait ConnectionDependentObject
{
    /** @var IConnection */
    private $conn = null;

    public function attachToConnection(IConnection $connection): void
    {
        $this->conn = $connection;
    }

    public function detachFromConnection(): void
    {
        $this->conn = null;
    }

    protected function getConnection(): IConnection
    {
        if ($this->conn === null) {
            throw new InvalidStateException('No connection is attached');
        }
        return $this->conn;
    }
}
