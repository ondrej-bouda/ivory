<?php
namespace Ivory\Relation;

use Ivory\Connection\IStatementExecution;
use Ivory\Exception\NotImplementedException;
use Ivory\Result\IQueryResult;

class QueryRelation extends RelationBase implements IQueryRelation
{
    private $conn;
    private $query;
    private $args;

    /** @var IQueryResult */
    private $result = null;

    public function __construct(IStatementExecution $conn, $query, ...$args)
    {
        parent::__construct();

        if ($args) {
            throw new NotImplementedException('args not implemented yet');
        }

        $this->conn = $conn;
        $this->query = $query;
        $this->args = $args;
    }

    public function getColumns()
    {
        $this->populate();
        return $this->result->getColumns();
    }

    public function getSql()
    {
        return $this->query;
    }

    public function col($offsetOrNameOrEvaluator)
    {
        $this->populate();
        return $this->result->col($offsetOrNameOrEvaluator);
    }

    public function tuple($offset = 0)
    {
        $this->populate();
        return $this->result->tuple($offset);
    }


    //region IteratorAggregate

    public function getIterator()
    {
        $this->populate();
        return $this->result->getIterator();
    }

    //endregion

    //region ICachingDataProcessor

    public function flush()
    {
        $this->result = null;
    }

    public function populate()
    {
        if ($this->result !== null) {
            return;
        }

        $this->result = $this->conn->rawQuery($this->getSql());
    }

    //endregion

    //region Countable

    public function count()
    {
        $this->populate();
        return $this->result->count();
    }

    //endregion
}
