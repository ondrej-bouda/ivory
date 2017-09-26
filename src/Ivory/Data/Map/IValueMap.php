<?php
namespace Ivory\Data\Map;

/**
 * Map of values.
 *
 * Multiple levels of mapping are possible, i.e., a map entry may in fact be another map.
 * At the last level of mapping, there is a single value.
 */
interface IValueMap extends IMappedObject
{
    /**
     * Returns the item mapped by the given key or series of keys.
     *
     * @param array ...$key
     * @return mixed|IValueMap the value, or inner map in case of multi-level map, mapped by the given key
     * @throws \OutOfBoundsException if the requested key is not present in the map;
     *                               for a more tolerant version, returning <tt>null</tt> instead of throwing exception,
     *                                 see {@link maybe()}
     */
    function get(...$key);

    /**
     * Returns the item mapped by the given key or series of keys, or <tt>null</tt> if there is no such item.
     *
     * @param array ...$key
     * @return mixed|IValueMap|null the value, or inner map in case of multi-level map, mapped by the given key, or
     *                                <tt>null</tt> if no entry is mapped by the given key
     */
    function maybe(...$key);

    /**
     * @return array list of all the mapping keys
     */
    function getKeys(): array;
}
