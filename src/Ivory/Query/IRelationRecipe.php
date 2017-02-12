<?php
namespace Ivory\Query;

use Ivory\Type\ITypeDictionary;

/**
 * Specification for a relation.
 */
interface IRelationRecipe
{
    /**
     * Makes an SQL query which, when executed, produces the desired relation.
     *
     * @param ITypeDictionary $typeDictionary
     * @return string SQL query string
     */
    function toSql(ITypeDictionary $typeDictionary) : string;

    /**
     * Creates this recipe limited only to certain number of rows, or starting from a certain row.
     *
     * @param int|null $limit the number of rows to limit this relation to; <tt>null</tt> means no limit
     * @param int $offset the number of leading rows to skip
     * @return IRelationRecipe recipe for relation containing at most <tt>$limit</tt> rows, skipping the first
     *                           <tt>$offset</tt> rows
     */
    function limit($limit, int $offset = 0);
}
