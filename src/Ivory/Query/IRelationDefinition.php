<?php
declare(strict_types=1);
namespace Ivory\Query;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Lang\Sql\ISqlSortExpression;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Type\ITypeDictionary;

/**
 * Specification for a relation.
 */
interface IRelationDefinition
{
    /**
     * Makes an SQL query which, when executed, produces the desired relation.
     *
     * @param ITypeDictionary $typeDictionary
     * @return string SQL query string
     */
    function toSql(ITypeDictionary $typeDictionary): string;

    /**
     * Creates the definition reduced only to tuples satisfying a given condition.
     *
     * @param ISqlPredicate|SqlPattern|string $cond SQL condition;
     *                                  either an {@link ISqlPredicate}, giving SQL string for the WHERE clause, or
     *                                  an {@link SqlPattern} or string specifying the pattern to be parsed - which
     *                                    means that the same rules apply to the string as for, e.g.,
     *                                    {@link IStatementExecution::query()}
     * @param array $args list of arguments to <tt>$cond</tt> if passed as a <tt>string</tt> or <tt>SqlPattern</tt>
     * @return IRelationDefinition relation containing only those tuples from this relation satisfying <tt>$cond</tt>
     */
    function where($cond, ...$args): IRelationDefinition;

    /**
     * Creates the definition sorted by one or more sort expressions.
     *
     * Examples:
     * <code>
     * $sorted = $relDef->sort('a > %', 0); // ...ORDER BY a > 0
     * $sorted = $relDef->sort(SqlRelationDefinition::fromPattern('a = %'), 3); // ...ORDER BY a = 3
     * $sorted = $relDef->sort([
     *     'a',
     *     ['b > %', 0],
     * ]); // ...ORDER BY a, b > 0
     * </code>
     *
     * @param ISqlSortExpression|SqlPattern|string|array $sortExpression the sort expression;
     *                                  either an {@link ISqlPredicate}, giving SQL string for the ORDER BY clause, or
     *                                  an {@link SqlPattern} or string specifying the pattern to be parsed - which
     *                                    means that the same rules apply to the string as for, e.g.,
     *                                    {@link IStatementExecution::query()}, or
     *                                  an array, specifying multiple sort expressions at once, where each item may
     *                                    either be an {@link ISqlPredicate}, {@link SqlPattern} or string pattern
     *                                    taking no arguments, or an array, the first item of which is a pattern and the
     *                                    rest items are the positional arguments for the pattern
     * @param array $args list of arguments to <tt>$sortExpression</tt> if passed as a <tt>string</tt> or
     *                      <tt>SqlPattern</tt>
     * @return IRelationDefinition relation sorted primarily by the given sort expression
     */
    function sort($sortExpression, ...$args): IRelationDefinition;

    /**
     * Creates the definition limited only to certain number of tuples or starting from a certain tuple.
     *
     * @param int|null $limit the number of tuples to limit this relation to; <tt>null</tt> means no limit
     * @param int $offset the number of leading tuples to skip
     * @return IRelationDefinition definition of relation containing at most <tt>$limit</tt> tuples, skipping the first
     *                               <tt>$offset</tt> tuples
     */
    function limit(?int $limit, int $offset = 0): IRelationDefinition;
}
