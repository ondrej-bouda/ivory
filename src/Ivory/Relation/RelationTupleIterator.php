<?php
namespace Ivory\Relation;

class RelationTupleIterator implements \Iterator
{
    private $rel;
    private $cnt;
    private $pos = 0;

    public function __construct(IRelation $rel)
    {
        $this->rel = $rel;
        $this->cnt = $rel->count();
    }


    public function current()
    {
        return $this->rel->tuple($this->pos);
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
        return ($this->pos < $this->cnt);
    }

    public function rewind()
    {
        $this->pos = 0;
    }
}
