<?php
namespace Ivory\Relation;

/**
 * Represents one relation row.
 *
 * From one point of view, a tuple is a `Traversable` list of values. The traversal order is the same as of the column
 * list of the originating relation. The column names are returned as traversal keys.
 *
 * From another point of view, a tuple is a readonly-`ArrayAccess`ible map of offsets or names of the originating
 * columns to the corresponding values.
 *
 * Note there might be several columns of the same name defined on the originating relation, or the column name might
 * not be defined at all. Thus:
 * - while traversing a tuple, the same key may be returned several times, or `null` may be returned as the traversal
 *   key for some columns;
 * - when accessing a value by the column name, value of the first column of the given name is returned, the rest being
 *   ignored.
 */
interface ITuple extends \ArrayAccess, \Traversable
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
    function toArray();

    /**
     * Converts the tuple to a list of values from the individual columns.
     *
     * @return array list of values of this tuple
     */
    function toList();
}
