<?php
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Utils\IComparable;

/**
 * Represents one relation row.
 *
 * From one point of view, a tuple is a `Traversable` list of values. The traversal order is the same as of the column
 * list of the originating relation. The column names are returned as traversal keys.
 *
 * From another point of view, a tuple is a map of offsets or names of the originating columns to the corresponding
 * values. There are two ways of accessing an attribute value:
 * - via the column (zero-based) offset using `ArrayAccess` (e.g., `$tuple[0]` is the value of the first column), or
 * - via the column name using either `ArrayAccess`, for non-numeric names, or dynamic property access (e.g., both
 *   `$tuple['foo']` and `$tuple->foo` expose the value of the column named "foo").
 *
 * Note there might be several columns of the same name defined on the originating relation, or the column name might
 * not be defined at all. Thus:
 * - while traversing a tuple, the same key may be returned several times, or `null` may be returned as the traversal
 *   key for some columns;
 * - when accessing a value by the column name, value of the first column of the given name is returned, the rest being
 *   ignored.
 */
interface ITuple extends \ArrayAccess, \Traversable, IComparable // TODO: test IComparable
{
    /**
     * Converts the tuple to an associative array of column names to the corresponding values held by this tuple.
     *
     * Note there might be several columns of the same name defined on the originating relation, or the column name
     * might not be defined at all. Only the value of the first of the name-colliding columns is returned, the others
     * are ignored, as well as values of unnamed columns. Thus, the returned array might have less items than columns.
     * {@link toList()} might come in handy in such situations.
     *
     * @return array map: column name => value
     */
    function toMap(): array;

    /**
     * Converts the tuple to a list of values from the individual columns.
     *
     * @return array list of values of this tuple
     */
    function toList(): array;

    /**
     * @param int|string|ITupleEvaluator|\Closure $colOffsetOrNameOrEvaluator
     *                                  Specification of column from which to get the value. See
     *                                    {@link IRelation::col()} for more details on the column specification.
     * @return mixed
     * @throws UndefinedColumnException if no column matches the specification
     */
    function value($colOffsetOrNameOrEvaluator = 0);

    /**
     * @return string[] list of column names the tuple consists of
     */
    function getColumnNames();

    /**
     * @param string $name column name
     * @return mixed value for the given column, or <tt>null</tt> if no such column is defined above the tuple
     */
    function __get($name);

    /**
     * @param string $name column name
     * @return bool whether the column is defined above the tuple
     */
    function __isset($name);
}
