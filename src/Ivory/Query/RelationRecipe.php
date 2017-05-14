<?php
namespace Ivory\Query;

abstract class RelationRecipe implements IRelationRecipe
{
    public function where($cond, ...$args): IRelationRecipe
    {
        return new ConstrainedRelationRecipe($this, $cond, ...$args);
    }

    public function sort($sortExpression, ...$args): IRelationRecipe
    {
        return new SortedRelationRecipe($this, $sortExpression, ...$args);
    }

    public function limit($limit, int $offset = 0): IRelationRecipe
    {
        return new LimitedRelationRecipe($this, $limit, $offset);
    }
}
