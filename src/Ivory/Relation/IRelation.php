<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\ITupleComparator;
use Ivory\Relation\Alg\ITupleFilter;
use Ivory\Relation\Alg\ITupleHasher;

interface IRelation extends \Traversable, ICachingDataProcessor
{
    /**
     * Reduces the relation only to tuples satisfying a given filter.
     *
     * @param ITupleFilter|\Closure $decider
     *                                  the decider which, given a tuple, tells whether it is passed to the result;
     *                                  an {@link ITupleFilter} gets called its {@link ITupleFilter::accept()} method;
     *                                  a <tt>Closure}</tt> is given one {@link ITuple} argument and is expected to
     *                                    return <tt>true</tt> or <tt>false</tt> telling whether the tuple is allowed
     * @return IRelation relation containing only those tuples from this relation, in their original order, which are
     *                   accepted by <tt>$decider</tt>
     */
    function filter($decider);

    function project(); // TODO

    /**
     * Projects a single column from this relation.
     *
     * @param string|ITupleEvaluator|\Closure $nameOrEvaluator Name of column to project from this relation, or an
     *                                    evaluator which computes each value from the tuple.
     *                                  As the evaluator, either {@link ITupleEvaluator}, the
     *                                    {@link ITupleEvaluator::evaluate()} method of which is called, or
     *                                    <tt>Closure</tt> may be used; the <tt>Closure</tt> is given one {@link ITuple}
     *                                    argument and is expected to return the value to use for the resulting column.
     * @return IColumn
     */
    function col($nameOrEvaluator);

    /**
     * Renames some columns in this relation.
     *
     * Columns not mentioned by the argument are left in the resulting relation as is.
     *
     * @param string[]|\Traversable $renamePairs old-new name map
     * @return IRelation the same relation as this one except that columns in <tt>$renamePairs</tt> are renamed
     */
    function rename($renamePairs);

    function assoc(); // TODO

    function hash(); // TODO

    function map(); // TODO

    function multimap(); // TODO

    /**
     * Reduces the relation only to unique tuples.
     *
     * The first of the group of equivalent tuples is always passed to the result, the others are dropped.
     *
     * Each tuple is hashed, first, using <tt>$hasher</tt>. In case of collision, i.e., if the tuple hashes to the same
     * value as any of the previous tuples, <tt>$comparator</tt> is used for deciding the equivalence precisely.
     *
     * Note the <tt>$hasher</tt> and <tt>$comparator</tt> must be consistent, i.e., tuples equal according to the
     * comparator must be hashed to the same value.
     *
     * {@internal The implementing method should document behaviour of the default tuple hasher and comparator.}
     *
     * @param ITupleHasher|\Closure|int $hasher hashes a tuple;
     *                                  an {@link ITupleHasher} gets called its {@link ITupleHasher::hash()} method;
     *                                  a <tt>Closure</tt> is given one {@link ITuple} argument and is expected to
     *                                    return the tuple hash as a <tt>string</tt> or <tt>int</tt>;
     *                                  the integer <tt>1</tt> may specially be used for a constant hasher, which
     *                                    effectively skips hashing and leads to only using <tt>$comparator</tt>;
     *                                  if not given, the default hasher provided by the implementing class is used
     * @param ITupleComparator|\Closure $comparator tells whether two tuples are equivalent or not;
     *                                  an {@link ITupleComparator} gets called its {@link ITupleComparator::equal()}
     *                                    method;
     *                                  a <tt>Closure</tt> is given two {@link ITuple} arguments and is expected to
     *                                    return <tt>true</tt> if it considers the tuples equivalent, or <tt>false</tt>
     *                                    otherwise;
     *                                  if not given, the default comparator provided by the implementing class is used
     * @return IRelation
     */
    function uniq($hasher = null, $comparator = null);

    /**
     * @return array[] list of associative arrays, each representing one tuple of this relation (in the original order);
     *                 column names are used as the associative array keys;
     *                 in case there are multiple columns of the same name in this relation, only the first of them is
     *                   exported to the result, the others are dropped ({@link rename()} or {@link project()} may be
     *                   used to name colliding columns differently)
     */
    function toArray();
}
