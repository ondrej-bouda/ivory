<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;

abstract class RelationBase implements \IteratorAggregate, IRelation
{
    use RelationMacros;


    protected function __construct()
    {
    }


    public function filter($decider): IRelation
    {
        return new FilteredRelation($this, $decider);
    }

    public function project($columns): IRelation
    {
        return new ProjectedRelation($this, $columns);
    }

    public function rename(iterable $renamePairs): IRelation
    {
        return new RenamedRelation($this, $renamePairs);
    }

    public function uniq($hasher = null, $comparator = null): IRelation
    {
        throw new NotImplementedException();
    }
}
