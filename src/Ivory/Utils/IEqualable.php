<?php
namespace Ivory\Utils;

/**
 * Defines logical equality on objects of the implementing class.
 */
interface IEqualable
{
    /**
     * @param object $object
     * @return bool|null <tt>true</tt> if <tt>$this</tt> and the other <tt>$object</tt> are equal to each other,
     *                   <tt>false</tt> if they are not equal,
     *                   <tt>null</tt> iff <tt>$object</tt> is <tt>null</tt>
     */
    function equals($object);
}
