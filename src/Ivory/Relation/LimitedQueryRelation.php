<?php
namespace Ivory\Relation;

use Ivory\Connection\IStatementExecution;

class LimitedQueryRelation extends QueryRelation
{
    private $limit;
    private $offset;

    /**
     * @param IStatementExecution $conn
     * @param string $query
     * @param int|null $limit
     * @param int $offset
     */
    public function __construct(IStatementExecution $conn, $query, $limit, $offset = 0)
    {
        parent::__construct($conn, $query);
        $this->limit = ($limit !== null ? (int)$limit : null);
        $this->offset = (int)$offset;
    }

    public function getSql()
    {
        return sprintf(
            "SELECT *\nFROM (\n%s\n) t%s%s",
            parent::getSql(),
            ($this->limit !== null ? "\nLIMIT {$this->limit}" : ''),
            ($this->offset ? "\nOFFSET {$this->offset}" : '')
        );
    }
}
