<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\ITupleComparator;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Relation\Alg\ITupleFilter;
use Ivory\Relation\Alg\ITupleHasher;

/**
 * A client-side relation.
 *
 * A relation is essentially a `Traversable` list of {@link ITuple}s, each conforming to the same scheme - the relation
 * columns.
 *
 * The relation itself might not actually hold the data (and for performance reasons, it usually will not). Instead, it
 * may be derived from another relation. From this point of view, a relation is an {@link ICachingDataProcessor}.
 */
interface IRelation extends \Traversable, ICachingDataProcessor
{
    /**
     * Reduces the relation only to tuples satisfying a given filter.
     *
     * @param ITupleFilter|\Closure $decider
     *                                  the decider which, given a tuple, tells whether it is passed to the result;
     *                                  an {@link ITupleFilter} gets called its {@link ITupleFilter::accept()} method;
     *                                  a <tt>Closure</tt> is given one {@link ITuple} argument and is expected to
     *                                    return <tt>true</tt> or <tt>false</tt> telling whether the tuple is allowed
     * @return IRelation relation containing only those tuples from this relation, in their original order, which are
     *                   accepted by <tt>$decider</tt>
     */
    function filter($decider);

    /**
     * Makes up a relation from this relation by redefining its columns.
     *
     * # Examples
     *
     * - `$rel->project(['a', 'b'])` narrows `$rel` only to columns `a` and `b`.
     * - `$rel->project(['a' => 'b', 'b' => 'a'])` narrows `$rel` only to columns `a` and `b`, swapping the values
     *   between them.
     * - `$rel->project(['sum' => function (ITuple $t) { return $t['a'] + $t['b']; }])` computes a relation containing
     *   `sum` as its only column, values of which are the sums from `$rel`'s columns `a` and `b`.
     * - `$rel->project(['p_*' => 'person_*'])` narrows `$rel` only to columns the names of which start with the prefix
     *   `person_`, and shortens the prefix to `p_` in the result relation.
     * - `$rel->project(['*', 'copy' => 'a')` extends `$rel` with column `copy` containing the same values as column
     *   `a`; the {@link extend()} method may be used for such a special case.
     * - `$rel->project(['a', '/_.*_/', '\1' => '/(.*)name$/i'])` narrows `$rel` to column `a`, columns the name of
     *   which contains at least two underscores, and columns the name of which ends with "name" (case insensitive) -
     *   omitting the "name" suffix.
     *
     * # Specification
     *
     * A completely new relation is composed on the base of this relation and the `$columns` definition. Each `$columns`
     * item either defines one column or, using a macro, multiple columns at once.
     *
     * ## Definition of a single column
     *
     * There are two forms of specifying a single column:
     * - The short form merely names an existing column from this relation. It is then copied to the result relation.
     * - The full form specifies both the array key and value of the `$columns` item - key specifying the name of the
     *   new column, value specifying its value. The value may either be a plain string, taking the values from an
     *   existing column of this relation, or a dynamic tuple evaluator, computing the value from the whole tuple. The
     *   tuple evaluator may either be a {@link ITupleEvaluator} object which gets called its
     *   {@link ITupleEvaluator::evaluate()} method, or a `Closure` which is given one {@link ITuple} argument holding
     *   the current tuple and is expected to return the value computed from the tuple for the column.
     *
     * Note the short and full form might collide. In such a case, i.e., if the item key is numeric and the item value
     * is a string, the short form is preferred.
     *
     * Note that if there are multiple same-named columns in this relation, the first of them is always used when
     * referring to the column by its name, and a notice is issued about it.
     *
     * ## Defining multiple columns using a macro
     *
     * Macros may be used for defining multiple columns at once by matching the column names to a pattern. Each of the
     * original columns matching the pattern is added to the result relation. If both the `$columns` item key and value
     * is specified, and if the key is not an integer, value is taken as the macro pattern while the key is taken as the
     * new column names pattern - called the "replacement pattern" in the following description. Note that, if not
     * treated properly, multiple original column names might lead to the same new column name. In such a case, the
     * result relation will contain all of them in their original order.
     *
     * There are two flavors of macros - the simple and the PCRE macros. A macro starting with a `/` (slash) is
     * considered as a PCRE macro, otherwise, it is assumed to be a simple macro.
     *
     * ### Simple macros
     *
     * A simple macro uses `*` (star) as the general wildcard symbol, standing for any (even an empty) substring.
     * A backslash may be used to write a star, a backslash, or a starting slash literally. Other characters are mere
     * literals. Multiple stars may be used in a single macro.
     *
     * The replacement pattern, if specified, may contain stars, too, each of which is replaced by the part of the
     * original column name matched by the corresponding star from the matching pattern.
     *
     * ### PCRE macros
     *
     * PCRE macros use PCREs for both the matching pattern and the replacement pattern. They are the same as for the
     * {@link preg_replace()} function. The only restriction is that they must use `/` (slash) as the PCRE pattern
     * separator.
     *
     * @param (string|ITupleEvaluator|\Closure)[]|\Traversable $columns
     *                                  the specification of new columns;
     *                                  if a <tt>Traversable</tt> object is given, it is guaranteed to be traversed only
     *                                    once
     * @return IRelation a new relation with columns according to the <tt>$columns</tt> specification
     */
    function project($columns);

    /**
     * Extends this relation with some extra columns.
     *
     * All columns from this relation are preserved, the extra columns added after them.
     *
     * The specification of the extra columns follows the same rules as for the {@link project()} method. In fact,
     * calling `$rel->extend($extraColumns)` is a mere shorthand for `$rel->project(array_merge(['*'], $extraColumns))`.
     *
     * @param (string|ITupleEvaluator|\Closure)[]|\Traversable $extraColumns
     *                                  the specification of the extra columns;
     *                                  if a <tt>Traversable</tt> object is given, it is guaranteed to be traversed only
     *                                    once
     * @return IRelation the same relation as this one, extended with extra columns
     */
    function extend($extraColumns);

    /**
     * Renames some columns in this relation.
     *
     * Columns not mentioned by the argument are left in the resulting relation as is.
     *
     * The same macros as for the {@link project()} method may be used for pattern-based renaming of several columns at
     * once.
     *
     * @param string[]|\Traversable $renamePairs old-new name map
     * @return IRelation the same relation as this one except that columns in <tt>$renamePairs</tt> are renamed
     */
    function rename($renamePairs);

    /**
     * Projects a single column from this relation.
     *
     * @param int|string|ITupleEvaluator|\Closure $offsetOrNameOrEvaluator
     *                                  Offset or name of column to project from this relation, or an evaluator which
     *                                    computes each value from the tuple.
     *                                  As an evaluator, either {@link ITupleEvaluator} (the
     *                                    {@link ITupleEvaluator::evaluate()} method of which is called) or
     *                                    <tt>Closure</tt> may be used; the <tt>Closure</tt> is given one {@link ITuple}
     *                                    argument and is expected to return the value to use for the resulting column.
     * @return IColumn
     */
    function col($offsetOrNameOrEvaluator);

    /**
     * Maps the tuples of this relation by one or more dimensions of keys.
     *
     * The relation gets enclosed in one or more {@link ITupleMapping} boxes, each for one dimension of mapping. The
     * innermost box maps individual {@link ITuple}s.
     *
     * Note that the last level of mapping (i.e., mapping according to the last argument) leads to elimination of
     * duplicates, as at most one tuple may be mapped by the combination of the mapping keys. If such a situation
     * happens, only the first tuple is considered, the other conflicting tuples are ignored, and a warning is issued.
     * Use {@link multimap()} to get a relation (i.e., *list* of tuples) instead of a single tuple.
     *
     * @param int|string|ITupleEvaluator|\Closure $mappingCols
     *                                  The mapping specification - one or more columns, each specifying values for one
     *                                    dimension of mapping.
     *                                  Specification of each mapping column is the same as for {@link col()}.
     * @return ITupleMapping
     */
    function map(...$mappingCols);

    /**
     * Divides this relation to several relations mapped by one or more keys.
     *
     * The relation gets enclosed by one or more {@link IRelationMapping} boxes, each for one dimension of mapping. The
     * innermost box maps the individual {@link IRelation}s.
     *
     * Note the box returned by this method still behaves as an {@link IRelation}. The semantics of further
     * {@link IRelation} operations called on the returned box are redirected to the innermost box which holds the
     * original relation being mapped - the operation is called on it and the result is stored to the innermost box
     * instead of the original relation. TODO: Think of a type-safe variant: extract IRelation operations which return
     * an IRelation to a separate IRelationManipulation interface, used both by IRelation and IRelationMapping. Then,
     * IRelationMapping would be guaranteed to return either IRelationMapping or IRelationManipulation. In either case,
     * the (interface) type would not depend on the operations called on the boxed IRelation, although it would lower
     * the flexibility - i.e., ->col() would not be allowed to be called on the result of this operation, but only on
     * the actual IRelation returned by the innermost box. However, the actual type of unreferencing a multi-boxed
     * relation cannot be inferred at compile time, i.e., the compiler cannot decide at any level whether the
     * unreferenced box is the innermost one and thus whether the returned object is a full-featured IRelation which
     * cannot be further unreferenced, but which has other IRelation methods, not just IRelationManipulation. Processing
     * the result would thus be too complicated and would need a type hint for the IDE to recognize the IRelation object
     * properly and offer further processing methods on it. Nonetheless, even in the less type-safe boxing model, some
     * type hints would be necessary. What about separating the IRelation methods by their return type, extracting them
     * to individual interfaces, and introducing a special I*Mapping interface for each? E.g., multimap() would return
     * an IRelationMapping object; calling ->col() on it would return an IColumnMapping object, or calling ->tuple() on
     * the IRelationMapping object would return an ITupleMapping object... In other words, the return type would mark
     * the type of the object (relation/tuple/value/column) held by the innermost box, restricting what is possible to
     * call on the box - relation/tuple/value/column operations interface plus Traversable and ArrayAccess for unboxing
     * the dimensions.
     *
     * @param $mappingCols
     * @return IRelationMapping
     */
    function multimap(...$mappingCols); // TODO: consider renaming the method to by(); imagine the calls: by('person_id')...

    /**
     * Associates values of one column by (combinations of) values of one or more columns.
     *
     * This is a mere shorthand for `$rel->multimap($leadArgs)->col($lastArg)`, where `$leadArgs` are all but the last
     * argument and `$lastArg` is the last argument of this method call. See {@link multimap()} and {@link col()} for
     * complete specification.
     *
     * @param int|string|ITupleEvaluator|\Closure $cols
     *                                  The association specification. The last column specifies the associated values,
     *                                    whereas the other columns specify the mapping.
     *                                  If not specified, all the relation columns are consecutively used as though they
     *                                    were arguments to this method call. E.g., <tt>$rel->assoc()</tt> on a relation
     *                                    consisting of 3 columns is equivalent to <tt>$rel->assoc(0, 1, 2)</tt>.
     * @return IMappedRelation
     */
    function assoc(...$cols);

    function hash(); // TODO

    /**
     * Reduces the relation only to unique tuples.
     *
     * The first of the group of equivalent tuples is always passed to the result, the others are dropped.
     *
     * Each tuple is hashed, first, using `$hasher`. In case of collision, i.e., if the tuple hashes to the same value
     * as any of the previous tuples, `$comparator` is used for deciding the equivalence precisely.
     *
     * Note the `$hasher` and `$comparator` must be consistent, i.e., tuples equal according to the comparator must be
     * hashed to the same value.
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
     *                   exported to the result, the others are ignored ({@link rename()} may be used to name colliding
     *                   columns differently)
     */
    function toArray();

    /**
     * Retrieves a single tuple from this relation.
     *
     * @param int $offset zero-based offset of the tuple to get
     * @return ITuple the `$offset`-th tuple of this relation
     * @throws \OutOfBoundsException when this relation has fewer than `$offset+1` tuples
     */
    function tuple($offset = 0);

    /**
     * @param int $tupleOffset zero-based offset of the tuple to get
     * @param int|string|ITupleEvaluator|\Closure $colOffsetOrNameOrEvaluator
     *                                  Specification of column from which to get the value. See {@link col()} for more
     *                                    details on the column specification.
     * @return mixed
     * @throws \OutOfBoundsException when this relation has fewer than `$tupleOffset+1` tuples
     */
    function value($tupleOffset = 0, $colOffsetOrNameOrEvaluator = 0);
}
