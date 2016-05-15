<?php
namespace Ivory\Result;

class QueryResultIterator implements \SeekableIterator
{
    private $queryResult;
    private $pos = 0;

    public function __construct(IQueryResult $queryResult)
    {
        $this->queryResult = $queryResult;
    }


    public function current()
    {
        return $this->queryResult->tuple($this->pos);
    }

    public function next()
    {
        $this->pos++;
    }

    public function key()
    {
        return $this->pos;
    }

    public function valid()
    {
        return ($this->pos < $this->queryResult->count());
    }

    public function rewind()
    {
        $this->pos = 0;
    }

    public function seek($position)
    {
        $this->pos = $position;
    }
}
