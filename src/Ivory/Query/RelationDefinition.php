<?php
declare(strict_types=1);

namespace Ivory\Query;

abstract class RelationDefinition implements IRelationDefinition
{
    public function where($cond, ...$args): IRelationDefinition
    {
        return new ConstrainedRelationDefinition($this, $cond, ...$args);
    }

    public function sort($sortExpression, ...$args): IRelationDefinition
    {
        return new SortedRelationDefinition($this, $sortExpression, ...$args);
    }

    public function limit(?int $limit, int $offset = 0): IRelationDefinition
    {
        return new LimitedRelationDefinition($this, $limit, $offset);
    }
}
