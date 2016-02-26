<?php
namespace Ivory\Utils;

// FIXME: the interface name is misleading - one would expect comparing in the sense of < and > operators on top of ==

/**
 * Tells how to compare objects of the implementing class.
 */
interface IComparable
{
    /**
     * @param object $object
     * @return bool|null <tt>true</tt> if <tt>$this</tt> and the other <tt>$object</tt> are equal to each other,
     *                   <tt>false</tt> if they are not equal,
     *                   <tt>null</tt> iff <tt>$object</tt> is <tt>null</tt>
     */
    function equals($object);
}
