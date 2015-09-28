<?php
namespace Ivory\Utils;

/**
 * Tells how to compare objects of the implementing class.
 */
interface IComparable
{
    /**
     * @param object $object
     * @return bool <tt>true</tt> if <tt>$this</tt> and the other <tt>$object</tt> are equal to each other,
     *              <tt>false</tt> otherwise
     */
    function equals($object);
}
