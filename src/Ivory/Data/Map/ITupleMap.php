<?php
declare(strict_types=1);
namespace Ivory\Data\Map;

use Ivory\Relation\ITuple;

/**
 * Map of tuples.
 *
 * Multiple levels of mapping are possible, i.e., a map entry may in fact be another map.
 * At the last level of mapping, there is a single {@link ITuple}.
 */
interface ITupleMap extends IMappedObject
{
    /**
     * Returns an item mapped by the given key or series of keys.
     *
     * @param array ...$key
     * @return ITuple|ITupleMap the tuple, or inner map in case of multi-level map, mapped by the given key
     * @throws \OutOfBoundsException if the requested key is not present in the map;
     *                               for a more tolerant version, returning <tt>null</tt> instead of throwing exception,
     *                                 see {@link maybe()}
     */
    function get(...$key);

    /**
     * Returns an item mapped by the given key or series of keys, or <tt>null</tt> if there is no such item.
     *
     * @param array ...$key
     * @return ITuple|ITupleMap|null the tuple, or inner map in case of multi-level map, mapped by the given key, or
     *                                 <tt>null</tt> if no entry is mapped by the given key
     */
    function maybe(...$key);

    /**
     * @return array list of all the mapping keys
     */
    function getKeys(): array;
}
