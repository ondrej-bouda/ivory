<?php
declare(strict_types=1);
namespace Ivory\Relation;

class RelationSeekableIterator implements \SeekableIterator
{
    private $relation;
    private $pos = 0;

    public function __construct(IRelation $relation)
    {
        $this->relation = $relation;
    }


    public function current()
    {
        return $this->relation->tuple($this->pos);
    }

    public function next()
    {
        $this->pos++;
    }

    public function key()
    {
        return $this->pos;
    }

    public function valid(): bool
    {
        return ($this->pos < $this->relation->count());
    }

    public function rewind()
    {
        $this->pos = 0;
    }

    public function seek($offset)
    {
        $this->pos = $offset;
    }
}
