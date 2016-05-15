<?php
namespace Ivory\Relation;

abstract class StreamlinedRelation extends RelationBase
{
    protected $source;

    public function __construct(IRelation $source)
    {
        parent::__construct();
        $this->source = $source;
    }


    public function getColumns()
    {
        return $this->source->getColumns();
    }

    public function getIterator()
    {
        return ($this->source instanceof \Iterator ? $this->source : new \IteratorIterator($this->source));
    }

    public function tuple($offset = 0)
    {
        return $this->source->tuple($offset);
    }

    public function col($offsetOrNameOrEvaluator)
    {
        return $this->source->col($offsetOrNameOrEvaluator);
    }

    public function count()
    {
        return $this->source->count();
    }
}
