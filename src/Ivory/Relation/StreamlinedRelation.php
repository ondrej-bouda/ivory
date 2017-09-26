<?php
declare(strict_types=1);

namespace Ivory\Relation;

abstract class StreamlinedRelation extends RelationBase
{
    protected $source;
    private $cols = null;
    private $colNameMap = null;

    public function __construct(IRelation $source)
    {
        parent::__construct();
        $this->source = $source;
    }


    public function getColumns(): array
    {
        if ($this->cols === null) {
            $this->cols = [];
            foreach ($this->source->getColumns() as $srcCol) {
                $this->cols[] = $srcCol->bindToRelation($this);
            }
        }

        return $this->cols;
    }

    private function getColNameMap()
    {
        if ($this->colNameMap === null) {
            $this->colNameMap = self::computeColNameMap($this->source);
        }
        return $this->colNameMap;
    }

    public function getIterator()
    {
        return ($this->source instanceof \Iterator ? $this->source : new \IteratorIterator($this->source));
    }

    public function tuple(int $offset = 0): ITuple
    {
        return $this->source->tuple($offset);
    }

    public function col($offsetOrNameOrEvaluator): IColumn
    {
        return $this->_colImpl($offsetOrNameOrEvaluator, $this->getColumns(), $this->getColNameMap(), $this);
    }

    public function count()
    {
        return $this->source->count();
    }
}
