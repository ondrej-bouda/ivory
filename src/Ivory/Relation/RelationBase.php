<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;

abstract class RelationBase implements \IteratorAggregate, IRelation
{
    use RelationMacros;


    public function getColumns()
    {
        throw new NotImplementedException();
    }

    public function filter($decider)
    {
        return new FilteredRelation($this, $decider);
    }

    public function project($columns)
    {
        throw new NotImplementedException();
    }

    public function rename($renamePairs)
    {
        throw new NotImplementedException();
    }

    public function map(...$mappingCols)
    {
        throw new NotImplementedException();
    }

    public function multimap(...$mappingCols)
    {
        throw new NotImplementedException();
    }

    public function assoc(...$cols)
    {
        throw new NotImplementedException();
    }

    public function hash($colOffsetOrNameOrEvaluator, $hasher = null)
    {
        throw new NotImplementedException();
    }

    public function uniq($hasher = null, $comparator = null)
    {
        throw new NotImplementedException();
    }
}
