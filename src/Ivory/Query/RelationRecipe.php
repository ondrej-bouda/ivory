<?php
namespace Ivory\Query;

use Ivory\Exception\NotImplementedException;

abstract class RelationRecipe implements IRelationRecipe
{
    public function limit($limit, int $offset = 0): IRelationRecipe
    {
        return new LimitedRelationRecipe($this, $limit, $offset);
    }

    public function where($cond, ...$args): IRelationRecipe
    {
        throw new NotImplementedException();
    }

    public function sort(...$sortExpressions): IRelationRecipe
    {
        throw new NotImplementedException();
    }
}
