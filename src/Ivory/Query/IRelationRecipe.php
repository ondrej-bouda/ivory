<?php
namespace Ivory\Query;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Lang\Sql\ISqlSortExpression;
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
     * Creates the recipe reduced only to tuples satisfying a given condition.
     *
     * @param ISqlPredicate|string $cond SQL condition
     * @param array $args list of arguments to <tt>$cond</tt> if passed as a <tt>string</tt>
     * @return IRelationRecipe relation containing only those tuples from this relation satisfying <tt>$cond</tt>
     */
    function where($cond, ...$args): IRelationRecipe;

    /**
     * Creates the recipe sorted by one or more sort expressions.
     *
     * @param (ISqlSortExpression|string)[] $sortExpressions
     *                                  one or more expressions to sort the relation by;
     *                                  the rows get sorted by the first expression, ties sorted by the second
     *                                    expression, etc.
     * @return IRelationRecipe
     */
    function sort(...$sortExpressions): IRelationRecipe;

    /**
     * Creates the recipe limited only to certain number of rows, or starting from a certain row.
     *
     * @param int|null $limit the number of rows to limit this relation to; <tt>null</tt> means no limit
     * @param int $offset the number of leading rows to skip
     * @return IRelationRecipe recipe for relation containing at most <tt>$limit</tt> rows, skipping the first
     *                           <tt>$offset</tt> rows
     */
    function limit($limit, int $offset = 0);
}
