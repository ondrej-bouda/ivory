<?php
namespace Ivory\Relation;

class RenamedRelationIterator extends \IteratorIterator
{
    private $columns;
    private $colNameMap;

    public function __construct(\Traversable $iterator, $columns, $colNameMap)
    {
        parent::__construct($iterator);
        $this->columns = $columns;
        $this->colNameMap = $colNameMap;
    }

    public function current()
    {
        $tuple = parent::current();
        return new Tuple($tuple->toList(), $this->columns, $this->colNameMap);
    }
}
