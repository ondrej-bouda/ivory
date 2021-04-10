<?php
declare(strict_types=1);
namespace Ivory\Data\Map;

use Ivory\Relation\IRelation;

/**
 * Map of relations.
 *
 * Multiple levels of mapping are possible, i.e., a map entry may in fact be another map.
 * At the last level of mapping, there is a single {@link IRelation}.
 */
interface IRelationMap extends IMappedObject
{
    /**
     * Returns the relation mapped by the given key or series of keys.
     *
     * @param string|int ...$key
     * @return IRelation|IRelationMap the relation, or inner map in case of multi-level map, mapped by the given key
     * @throws \OutOfBoundsException if the requested key is not present in the map;
     *                               for a more tolerant version, returning <tt>null</tt> instead of throwing exception,
     *                                 see {@link maybe()}
     */
    function get(...$key);

    /**
     * Returns the relation mapped by the given key or series of keys, or <tt>null</tt> if there is no such relation.
     *
     * @param string|int ...$key
     * @return IRelation|IRelationMap|null the relation, or inner map in case of multi-level map, mapped by the given
     *                                          key, or <tt>null</tt> if no entry is mapped by the given key
     */
    function maybe(...$key);

    /**
     * @return array list of all the mapping keys
     */
    function getKeys(): array;
}
