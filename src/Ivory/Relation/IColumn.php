<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\IValueComparator;
use Ivory\Relation\Alg\IValueFilter;
use Ivory\Relation\Alg\IValueHasher;

/**
 * Column of a relation.
 *
 * A column is essentially a `Traversable` list of values of the same type.
 *
 * The column itself might not actually hold the data (and for performance reasons, it usually will not). Instead, it
 * may be derived from its originating relation. From this point of view, a column is an {@link ICachingDataProcessor}.
 *
 * Unless computed by a client-side evaluator, a column is characterized by its name and data type of its values.
 */
interface IColumn extends \Traversable, ICachingDataProcessor
{
	/**
	 * @return string|null name of the column, if defined
	 */
	function getName();

	/**
	 * Reduces the column data only to values satisfying a given filter.
	 *
	 * @param IValueFilter|\Closure $decider
	 *                                  the decider which, given a value, tells whether it is passed to the result;
	 *                                  an {@link IValueFilter} gets called its {@link IValueFilter::accept()} method;
	 *                                  a <tt>Closure}</tt> is given a value as its only argument and is expected to
	 *                                    return <tt>true</tt> or <tt>false</tt> telling whether the value is allowed
	 * @return IColumn column containing only those values from this column, in their original order, which are accepted
	 *                   by <tt>$decider</tt>
	 */
	function filter($decider);

	/**
	 * Reduces the column only to unique values.
	 *
	 * The first of the group of equivalent values is always passed to the result, the others are dropped.
	 *
	 * Each value is hashed, first, using `$hasher`. In case of collision, i.e., if the value hashes to the same hash as
	 * any of the previous values, `$comparator` is used for deciding the equivalence precisely.
	 *
	 * Note the `$hasher` and `$comparator` must be consistent, i.e., values equal according to the comparator must be
	 * hashed to the same hash.
	 *
	 * {@internal The implementing method should document behaviour of the default value hasher and comparator.}
	 *
	 * @param IValueHasher|\Closure|int $hasher hashes a value;
	 *                                  an {@link IValueHasher} gets called its {@link IValueHasher::hash()} method;
	 *                                  a <tt>Closure</tt> is given a value as its only argument and is expected to
	 *                                    return the value hash as a <tt>string</tt> or <tt>int</tt>;
	 *                                  the integer <tt>1</tt> may specially be used for a constant hasher, which
	 *                                    effectively skips hashing and leads to only using <tt>$comparator</tt>;
	 *                                  if not given, the default hasher provided by the implementing class is used
	 * @param IValueComparator|\Closure $comparator tells whether two values are equivalent or not;
	 *                                  an {@link IValueComparator} gets called its {@link IValueComparator::equal()}
	 *                                    method;
	 *                                  a <tt>Closure</tt> is given two values as its arguments and is expected to
	 *                                    return <tt>true</tt> if it considers the values equivalent, or <tt>false</tt>
	 *                                    otherwise;
	 *                                  if not given, the default comparator provided by the implementing class is used
	 * @return IColumn
	 */
	function uniq($hasher = null, $comparator = null);

	/**
	 * @return array list of values of this column, in their original order
	 */
	function toArray();
}
