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
    function getQuery();

    /**
     * Reduces the relation only to tuples satisfying a given filter.
     *
     * On top of argument types accepted by the base {@link IRelation::filter()} method, it also accepts an SQL
     * condition applied to the query at the database server.
     *
     * @param ISqlPredicate|string|ITupleFilter|\Closure $condOrDecider
     *                                  either an SQL condition (as an {@link ISqlPredicate} or a `string`), or decider
     *                                    which, given a tuple, tells whether the tuple gets passed to the result;
     *                                  an {@link ITupleFilter} gets called its {@link ITupleFilter::accept()} method;
     *                                  a <tt>Closure</tt> is given one {@link ITuple} argument and is expected to
     *                                    return <tt>true</tt> or <tt>false</tt> telling whether the tuple is allowed
     * @return IRelation relation containing only those tuples from this relation, in their original order, which
     *                   satisfy the, or which are accepted by, the <tt>$condOrDecider</tt>
     */
    function filter($condOrDecider);

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
     * @param int|null $offset the number of leading rows to skip
     * @return IQueryRelation relation containing at most <tt>$limit</tt> rows, skipping the first <tt>$offset</tt> rows
     */
    function limit($limit, $offset = 0);
}
