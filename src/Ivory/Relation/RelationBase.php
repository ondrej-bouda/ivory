<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;

abstract class RelationBase implements \IteratorAggregate, IRelation
{
    use RelationMacros;


    public function __construct()
    {
    }


    public function filter($decider)
    {
        return new FilteredRelation($this, $decider);
    }

    public function project($columns)
    {
        return new ProjectedRelation($this, $columns);
    }

    public function rename($renamePairs)
    {
        return new RenamedRelation($this, $renamePairs);
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

    public function uniq($hasher = null, $comparator = null)
    {
        throw new NotImplementedException();
    }
}
