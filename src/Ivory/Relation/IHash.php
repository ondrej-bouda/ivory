<?php
namespace Ivory\Result;
use Ivory\Relation\Alg\IValueHasher;

/**
 * A hash of values. The only operation on a hash is checking whether a value was hashed to the given hash.
 */
interface IHash
{
	/**
	 * @param string|int $hash hash to check presence of
	 * @return bool <tt>true</tt> iff this hash contains <tt>$hash</tt>, i.e., a value was hashed to the given hash
	 */
	function containsHash($hash);

	/**
	 * @param mixed $value value to check presence of
	 * @param IValueHasher|\Closure $hasher hashes <tt>$value</tt> to check the presence of;
	 *                                  an {@link IValueHasher} gets called its {@link IValueHasher::hash()} method;
	 *                                  a <tt>Closure</tt> is given <tt>$value</tt> as its only argument and is expected
	 *                                    to return the value hash as a <tt>string</tt> or <tt>int</tt>;
     *                                  if not given, the default hasher provided by the implementing class is used
	 * @return bool <tt>true</tt> iff this hash contains the hash of <tt>$value</tt>, hashed by the given hasher
	 */
	function containsHashOf($value, $hasher = null);
}
