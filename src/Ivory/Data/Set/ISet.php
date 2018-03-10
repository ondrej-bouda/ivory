<?php
declare(strict_types=1);
namespace Ivory\Data\Set;

/**
 * A set of values.
 *
 * Stores items and allows one to ask whether an item is present in the set.
 *
 * The implementing class may restrict the types of items which may be stored in the set.
 */
interface ISet extends \Countable
{
    /**
     * @param mixed $value value to check presence of
     * @return bool <tt>true</tt> iff this set contains the <tt>$value</tt>
     */
    function contains($value): bool;

    /**
     * @param mixed $value value to add to the set
     */
    function add($value): void;

    /**
     * @param mixed $value value to remove from the set
     */
    function remove($value): void;

    /**
     * Removes all items from the set.
     */
    function clear(): void;

    /**
     * @return array array of all items in the set; note that no specific mutual order of the items is guaranteed
     */
    function toArray(): array;

    /**
     * Returns a generator which returns the items from this set.
     *
     * Note that the items may be returned in any mutual order.
     *
     * Note that modifications done to the set while using the generator are not reflected in the values returned by the
     * generator, i.e., the values which are contained in the set in the moment of invoking this method are generated.
     *
     * @return \Generator
     */
    function generateItems(): \Generator;
}
