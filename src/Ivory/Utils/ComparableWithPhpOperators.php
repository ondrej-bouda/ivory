<?php
namespace Ivory\Utils;

/**
 * Provides an implementation of the {@link IComparable} interface, comparing objects using the PHP `==` operator.
 */
trait ComparableWithPhpOperators
{
    final public function equals($object)
    {
        if ($object === null) {
            return null;
        }
        else {
            return ($this == $object);
        }
    }
}
