<?php
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
	function contains($value);

	/**
	 * @param mixed $value value to add to the set
	 */
	function add($value);

	/**
	 * @param mixed $value value to remove from the set
	 */
	function remove($value);
}
