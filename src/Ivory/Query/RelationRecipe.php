<?php
namespace Ivory\Query;

abstract class RelationRecipe implements IRelationRecipe
{
    /**
     * Creates this recipe limited only to certain number of rows, or starting from a certain row.
     *
     * @param int|null $limit the number of rows to limit this relation to; <tt>null</tt> means no limit
     * @param int $offset the number of leading rows to skip
     * @return IRelationRecipe recipe for relation containing at most <tt>$limit</tt> rows, skipping the first
     *                           <tt>$offset</tt> rows
     */
    public function limit($limit, int $offset = 0)
    {
        return new LimitedRelationRecipe($this, $limit, $offset);
    }
}
