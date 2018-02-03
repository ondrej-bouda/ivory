<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Value\Alg\IValueEqualizer;
use Ivory\Value\Alg\IValueFilter;
use Ivory\Value\Alg\IValueHasher;
use Ivory\Type\IType;

/**
 * Column of a relation.
 *
 * A column is essentially a `Countable` `Traversable` list of values of the same type.
 *
 * Unless computed by a client-side evaluator, a column is characterized by its name and data type of its values.
 */
interface IColumn extends \Traversable, \Countable
{
    /**
     * @return string|null name of the column, if defined
     */
    function getName(): ?string;

    /**
     * @return IType|null type of the column if known (it is unknown for columns on tuple evaluators)
     */
    function getType(): ?IType;

    /**
     * @param string $newName
     * @return IColumn a new column - copy of this column - with the given name
     */
    function renameTo(string $newName): IColumn;

    /**
     * @param IRelation $relation the new relation to bind the column to
     * @return IColumn a new column - copy of this column - bound to the given relation
     */
    function bindToRelation(IRelation $relation): IColumn;

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
    function filter($decider): IColumn;

    /**
     * Reduces the column only to unique values.
     *
     * The first of the group of equivalent values is always passed to the result, the others are dropped.
     *
     * Each value is hashed, first, using `$hasher`. In case of collision, i.e., if the value hashes to the same hash as
     * any of the previous values, `$equalizer` is used for deciding the equivalence precisely.
     *
     * Note the `$hasher` and `$equalizer` must be consistent, i.e., values equal according to the equalizer must be
     * hashed to the same key.
     *
     * {@internal The implementing method should document behaviour of the default value hasher and equalizer.}
     *
     * @param IValueHasher|\Closure|int $hasher hashes a value;
     *                                  an {@link IValueHasher} gets called its {@link IValueHasher::hash()} method;
     *                                  a <tt>Closure</tt> is given a value as its only argument and is expected to
     *                                    return the value hash as a <tt>string</tt> or <tt>int</tt>;
     *                                  the integer <tt>1</tt> may specially be used for a constant hasher, which
     *                                    effectively skips hashing and leads to only using <tt>$equalizer</tt>;
     *                                  if not given, the default hasher provided by the implementing class is used
     * @param IValueEqualizer|\Closure $equalizer tells whether two values are equivalent or not;
     *                                  an {@link IValueEqualizer} gets called its {@link IValueEqualizer::equal()}
     *                                    method;
     *                                  a <tt>Closure</tt> is given two values as its arguments and is expected to
     *                                    return <tt>true</tt> if it considers the values equivalent, or <tt>false</tt>
     *                                    otherwise;
     *                                  if not given, the default equalizer provided by the implementing class is used
     * @return IColumn
     */
    function uniq($hasher = null, $equalizer = null): IColumn;

    /**
     * @return array list of values of this column, in their original order
     */
    function toArray(): array;

    /**
     * @param int $valueOffset zero-based offset of the value to get;
     *                         if negative, the <tt>-$valueOffset</tt>'th value from the end is returned
     * @return mixed
     * @throws \OutOfBoundsException when this column has fewer than <tt>$valueOffset+1</tt> values, or fewer than
     *                                 <tt>-$valueOffset</tt> tuples if <tt>$valueOffset</tt> is negative
     */
    function value(int $valueOffset = 0);
}
