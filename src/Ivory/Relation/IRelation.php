<?php
namespace Ivory\Relation;

use Ivory\Data\Set\ISet;
use Ivory\Exception\AmbiguousException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleComparator;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Relation\Alg\ITupleFilter;
use Ivory\Relation\Alg\ITupleHasher;
use Ivory\Data\Map\IRelationMap;
use Ivory\Data\Map\ITupleMap;
use Ivory\Data\Map\IValueMap;

/**
 * A client-side relation.
 *
 * A relation is essentially a `Countable` `Traversable` list of {@link ITuple}s, each conforming to the same scheme -
 * the relation columns. Note that the relation does not implement `ArrayAccess` - use the {@link IRelation::tuple()}
 * method to get to specific rows.
 */
interface IRelation extends \Traversable, \Countable
{
    /**
     * @return IColumn[] list of columns constituting this relation
     */
    function getColumns(): array;

    /**
     * Reduces the relation only to tuples satisfying a given filter.
     *
     * @param ITupleFilter|\Closure $decider
     *                                  the decider which, given a tuple, tells whether the tuple gets passed to the
     *                                    result;
     *                                  an {@link ITupleFilter} gets called its {@link ITupleFilter::accept()} method;
     *                                  a <tt>Closure</tt> is given one {@link ITuple} argument and is expected to
     *                                    return <tt>true</tt> or <tt>false</tt> telling whether the tuple is allowed
     * @return IRelation relation containing only those tuples from this relation, in their original order, which are
     *                   accepted by <tt>$decider</tt>
     */
    function filter($decider): IRelation;

    /**
     * Makes up a relation from this relation by redefining its columns.
     *
     * # Examples
     *
     * - `$rel->project(['a', 'b'])` narrows `$rel` only to columns `a` and `b`.
     * - `$rel->project(['a' => 'b', 'b' => 'a'])` narrows `$rel` only to columns `a` and `b`, swapping the values
     *   between them.
     * - `$rel->project([1, 'x' => 0])` puts the second column of `$rel` as the first column in the result, followed by
     *   column named `x` having values from the first `$rel` column.
     * - `$rel->project(['sum' => function (ITuple $t) { return $t['a'] + $t['b']; }])` computes a relation containing
     *   `sum` as its only column, values of which are the sums from `$rel`'s columns `a` and `b`.
     * - `$rel->project(['p_*' => 'person_*'])` narrows `$rel` only to columns the names of which start with the prefix
     *   `person_`, and shortens the prefix to `p_` in the result relation.
     * - `$rel->project(['*', 'copy' => 'a'])` extends `$rel` with column `copy` containing the same values as column
     *   `a`; the {@link extend()} method may alternatively be used for such a special case.
     * - `$rel->project(['a', '/_.*_/', '\1' => '/(.*)name$/i'])` narrows `$rel` to column `a`, columns the names of
     *   which contain at least two underscores, and columns the names of which end with "name" (case insensitive) -
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
     * - The short form merely refers to an existing column from this relation. It is then copied to the result
     *   relation. A column may either be referred to by its name or zero-based position.
     * - The full form specifies both the array key and value of the `$columns` item - key specifying the name of the
     *   new column, value specifying its value. The value may either be a plain string or integer referring to values
     *   of an existing column of this relation (similarly to the short form mentioned above), or a dynamic tuple
     *   evaluator, computing the value from the whole tuple. The tuple evaluator may either be an
     *   {@link ITupleEvaluator} object which gets called its {@link ITupleEvaluator::evaluate()} method, or a `Closure`
     *   which is given one {@link ITuple} argument holding the current tuple and is expected to return the value
     *   computed from the tuple for the column.
     *
     * Note the short and full form might collide. In such a case, i.e., if the item key is numeric and the item value
     * is a string or integer, the short form is preferred.
     *
     * Note that if there are multiple same-named columns in this relation, the first of them is always used when
     * referring to the column by its name.
     *
     * ## Defining multiple columns at once using a macro
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
     * original column name matched by the corresponding star from the matching pattern. Again, to use the star
     * character literally, escape it with a backslash, as well as to use a literal backslash.
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
     * @throw UndefinedColumnException if any <tt>$columns</tt> item refers to an undefined column; this also includes
     *                                   macros - each macro must refer to at least one column
     * @throws AmbiguousException if any <tt>$columns</tt> item refers to a column by its name, which is used by
     *                              multiple columns
     */
    function project($columns): IRelation; // TODO: decide whether it is rather expected to interpret the key and values the other way round (see the ProcessingTest)

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
    function extend($extraColumns): IRelation;

    /**
     * Renames some columns in this relation.
     *
     * Columns not mentioned by the argument are left in the resulting relation as is.
     *
     * Alternatively to original column names, integers may also be used as rename mapping keys to refer to columns by
     * their zero-based position.
     *
     * The same macros as for the {@link project()} method may be used for pattern-based renaming of several columns at
     * once. In case multiple macros would match a single column, the column gets renamed by the macro which appears
     * first in the `$renamePairs` map.
     *
     * If there are multiple columns matching a macro, or even multiple columns of the same name, they all get renamed.
     *
     * @param iterable $renamePairs old-new name map
     * @return IRelation the same relation as this one except that columns in <tt>$renamePairs</tt> are renamed
     */
    function rename(iterable $renamePairs): IRelation;

    /**
     * Projects a single column from this relation.
     *
     * @param int|string|ITupleEvaluator|\Closure $offsetOrNameOrEvaluator
     *                                  Zero-based offset or (non-numeric) name of column to project from this relation,
     *                                    or an evaluator which computes each value from the tuple.
     *                                  As an evaluator, either {@link ITupleEvaluator} (the
     *                                    {@link ITupleEvaluator::evaluate()} method of which is called) or
     *                                    <tt>Closure</tt> may be used; the <tt>Closure</tt> is given one {@link ITuple}
     *                                    argument and is expected to return the value to use for the resulting column.
     * @return IColumn
     * @throws UndefinedColumnException if no column matches the specification
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function col($offsetOrNameOrEvaluator): IColumn;

//    function colData($offsetOrNameOrEvaluator): Iterator;

    /**
     * Associates values of one column by (combinations of) values of one or more columns.
     *
     * The association is specified by two or more columns. The last column specifies the associated values, whereas all
     * the preceding columns specify the mapping. Specification of each column is the same as for {@link col()}.
     *
     * E.g., if this was a relation of three columns, `id`, `firstname`, and `lastname`, then
     * ```
     * <?php
     * $this->assoc('id', function (ITuple $t) { return $t['firstname'] . ' ' . $t['lastname']; });
     * ```
     * would return an {@link IValueMap} with person IDs as keys and their full names as values.
     *
     * Note that the last level of association (i.e., mapping according to the last but one argument) leads to
     * elimination of duplicates, as at most one value may be associated by the combination of the mapping keys. If such
     * a situation happens, only the first tuple is considered, the other conflicting tuples are ignored, and a warning
     * is issued.
     *
     * @param int|string|ITupleEvaluator|\Closure $col1 the first column specifying the association
     * @param int|string|ITupleEvaluator|\Closure $col2 the first column specifying the association
     * @param array ...$moreCols optional additional columns specifying further dimensions of the association, each of
     *                             type <tt>(int|string|ITupleEvaluator|\Closure)</tt>
     * @return IValueMap
     * @throws UndefinedColumnException if there is no column matching the specification of an argument
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function assoc($col1, $col2, ...$moreCols): IValueMap;

    /**
     * Maps the tuples of this relation by one or more dimensions of keys.
     *
     * The mapping is specified by one or more columns, each defining values for one dimension of mapping. Specification
     * of each mapping column is the same as for {@link col()}.
     *
     * The relation gets enclosed in one or more {@link ITupleMap} boxes, each for one dimension of mapping. The
     * innermost box maps individual {@link ITuple}s.
     *
     * Note that the last level of mapping (i.e., mapping according to the last argument) leads to elimination of
     * duplicates, as at most one tuple may be mapped by the combination of the mapping keys. If such a situation
     * happens, only the first tuple is considered, the other conflicting tuples are ignored, and a warning is issued.
     * Use {@link multimap()} to get a relation (i.e., *list* of tuples) instead of a single tuple.
     *
     * See also {@link assoc()} to get just single values, or custom values computed from tuples, instead of tuples
     * themselves. In fact, `map()` is a specialized {@link assoc()} - it is essentially a shortcut for
     * `$this->assoc(...$mappingCols, function (ITuple $t) { return $t; })`.
     *
     * @param int|string|ITupleEvaluator|\Closure $mappingCol
     *                                  the first column specifying the mapping
     * @param array ...$moreMappingCols optional additional columns specifying further dimensions of the mapping, each
     *                                    of type <tt>(int|string|ITupleEvaluator|\Closure)</tt>
     * @return ITupleMap
     * @throws UndefinedColumnException if there is no column matching the specification of an argument
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function map($mappingCol, ...$moreMappingCols): ITupleMap;

    /**
     * Divides this relation to several relations mapped by one or more keys.
     *
     * The mapping is specified by one or more columns, each defining values for one dimensions of mapping.
     * Specification of each mapping column is the same as for {@link col()}.
     *
     * The relation gets enclosed by one or more {@link IRelationMap} boxes, each for one dimension of mapping. The
     * innermost box maps the individual {@link IRelation}s.
     *
     * @param int|string|ITupleEvaluator|\Closure $mappingCol
     *                                  the first column specifying the mapping
     * @param array ...$moreMappingCols optional additional columns specifying further dimensions of the mapping, each
     *                                    of type <tt>(int|string|ITupleEvaluator|\Closure)</tt>
     * @return IRelationMap
     * @throws UndefinedColumnException if there is no column matching the specification of an argument
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function multimap($mappingCol, ...$moreMappingCols): IRelationMap;

    /**
     * Makes a set of values of a column which is able to tell whether there was at least one tuple with the value of
     * the column equal to a given value.
     *
     * Note the returned object stores the values of the given column only, it is unable to return the original tuple,
     * however. Use {@link map()} instead for such a purpose.
     *
     * @param int|string|ITupleEvaluator|\Closure $colOffsetOrNameOrEvaluator
     *                                  Specification of column the values of which to hash. See {@link col()} for more
     *                                    details on the column specification.
     * @param ISet|null $set A set to add the values to. If not given, the default type of set is provided by the
     *                         implementing class is used.
     * @return ISet <tt>$set</tt>, or a newly created set
     * @throws UndefinedColumnException if no column matches the specification
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function toSet($colOffsetOrNameOrEvaluator, ?ISet $set = null): ISet;

    /**
     * Reduces the relation only to unique tuples.
     *
     * The first of the group of equivalent tuples is always passed to the result, the others are dropped.
     *
     * Each tuple is hashed, first, using `$hasher`. In case of collision, i.e., if the tuple hashes to the same value
     * as any of the previous tuples, `$comparator` is used for deciding the equivalence precisely.
     *
     * Note the `$hasher` and `$comparator` must be consistent, i.e., tuples equal according to the comparator must be
     * hashed to the same key.
     *
     * {@internal The implementing method should document behaviour of the default tuple hasher and comparator.}
     *
     * @param ITupleHasher|\Closure|int $hasher hashes a tuple;
     *                                  An {@link ITupleHasher} gets called its {@link ITupleHasher::hash()} method.
     *                                  A <tt>Closure</tt> is given one {@link ITuple} argument and is expected to
     *                                    return the tuple hash as a <tt>string</tt> or <tt>int</tt>.
     *                                  The integer <tt>1</tt> may specially be used for a constant hasher, which
     *                                    effectively skips hashing and leads to only using <tt>$comparator</tt>.
     *                                  If not given, the default hasher provided by the implementing class is used.
     * @param ITupleComparator|\Closure $comparator Tells whether two tuples are equivalent or not.
     *                                  An {@link ITupleComparator} gets called its {@link ITupleComparator::equal()}
     *                                    method.
     *                                  A <tt>Closure</tt> is given two {@link ITuple} arguments and is expected to
     *                                    return <tt>true</tt> if it considers the tuples equivalent, or <tt>false</tt>
     *                                    otherwise.
     *                                  If not given, the default comparator provided by the implementing class is used.
     * @return IRelation
     */
    function uniq($hasher = null, $comparator = null): IRelation; // TODO: test & implement

    /**
     * @return array[] list of associative arrays, each representing one tuple of this relation (in the original order);
     *                 column names are used as the associative array keys;
     *                 in case there are multiple columns of the same name in this relation, only the first of them is
     *                   exported to the result, the others are ignored ({@link rename()} may be used to name the
     *                   colliding columns differently)
     */
    function toArray(): array;

    /**
     * Retrieves a single tuple from this relation.
     *
     * @param int $offset zero-based offset of the tuple to get;
     *                    if negative, the <tt>-$offset</tt>'th tuple from the end is returned
     * @return ITuple the `$offset`-th tuple of this relation
     * @throws \OutOfBoundsException when this relation has fewer than <tt>$offset+1</tt> tuples, or fewer than
     *                                 <tt>-$tupleOffset</tt> tuples if <tt>$tupleOffset</tt> is negative
     */
    function tuple(int $offset = 0): ITuple;

    /**
     * @param int|string|ITupleEvaluator|\Closure $colOffsetOrNameOrEvaluator
     *                                  Specification of column from which to get the value. See {@link col()} for more
     *                                    details on the column specification.
     * @param int $tupleOffset zero-based offset of the tuple to get;
     *                         if negative, the <tt>-$tupleOffset</tt>'th tuple from the end is returned
     * @return mixed
     * @throws \OutOfBoundsException when this relation has fewer than <tt>$tupleOffset+1</tt> tuples, or fewer than
     *                                 <tt>-$tupleOffset</tt> tuples if <tt>$tupleOffset</tt> is negative
     * @throws UndefinedColumnException if no column matches the specification
     * @throws AmbiguousException if referring to the column by its name, which is used by multiple columns
     */
    function value($colOffsetOrNameOrEvaluator = 0, int $tupleOffset = 0);
}
