<?php
namespace Ivory\Relation;

/**
 * Represents one relation row.
 *
 * From one point of view, a tuple is a <tt>Traversable</tt> list of values. The traversal order is the same as of the
 * column list of the originating relation. The column names are returned as traversal keys.
 *
 * From another point of view, a tuple is a readonly-<tt>ArrayAccess</tt>ible map of names of the originating columns to
 * the corresponding values.
 *
 * Note there might be several columns of the same name defined on the originating relation, or the column name might
 * not be defined. Thus:
 * - while traversing a tuple, the same key may be returned several times, or <tt>null</tt> may be
 *   returned as the traversal key for some columns;
 * - when accessing a value by the column name, value of the first column of the given name is returned, the rest being
 *   ignored.
 */
interface ITuple extends \ArrayAccess, \Traversable
{
    /**
     * @return array associative array of column names to the corresponding values held by this tuple
     */
    function toArray();
}
