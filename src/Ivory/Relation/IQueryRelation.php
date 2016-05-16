<?php
namespace Ivory\Relation;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Relation\Alg\ITupleFilter;

/**
 * Relation defined by an SQL query.
 */
interface IQueryRelation extends IRelation
{
    /**
     * @return string the SQL query to be executed for this relation
     */
    function getSql();

    /**
     * Returns this relation reduced only to tuple satisfying a given condition.
     *
     * In contrast to the generic {@link IRelation::filter()} method, this method applies the condition at the database
     * server.
     *
     * @param ISqlPredicate|string $cond SQL condition
     * @param array $args list of arguments to <tt>$cond</tt> if passed as a <tt>string</tt>
     * @return IQueryRelation relation containing only those tuples from this relation satisfying <tt>$cond</tt>
     */
    function where($cond, ...$args);

    /**
     * Returns this relation sorted by one or more sort expressions.
     *
     * @param (ISqlSortExpression|string)[] $sortExpressions
     *                                  one or more expressions to sort the relation by;
     *                                  the rows get sorted by the first expression, ties sorted by the second
     *                                    expression, etc.
     * @return IQueryRelation
     */
    function sort(...$sortExpressions);

    /**
     * Returns this relation limited only to certain number of rows, or starting from a certain row.
     *
     * @param int|null $limit the number of rows to limit this relation to; <tt>null</tt> means no limit
     * @param int $offset the number of leading rows to skip
     * @return IQueryRelation relation containing at most <tt>$limit</tt> rows, skipping the first <tt>$offset</tt> rows
     */
    function limit($limit, $offset = 0);
}
