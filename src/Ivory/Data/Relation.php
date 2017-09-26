<?php
declare(strict_types=1);

namespace Ivory\Data;

use Ivory\Connection\IConnection;

class Relation
{
    /** @var IConnection */
    private $connection = null;

    public function setConnection(IConnection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): IConnection
    {
        if ($this->connection === null) {
            $this->connection = \Ivory\Ivory::getConnection();
        }

        return $this->connection;
    }
}
