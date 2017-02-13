<?php
namespace Ivory\Data;

class Relation
{
    /** @var \Ivory\Connection\IConnection */
    private $connection = null;

    public function setConnection(\Ivory\Connection\IConnection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = \Ivory\Ivory::getConnection();
        }

        return $this->connection;
    }
}
