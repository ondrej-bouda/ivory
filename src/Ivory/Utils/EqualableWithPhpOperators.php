<?php
namespace Ivory\Utils;

/**
 * Provides an implementation of the {@link IEqualable} interface, comparing objects using the PHP `==` operator.
 */
trait EqualableWithPhpOperators
{
    final public function equals($object)
    {
        if ($object === null) {
            return null;
        } else {
            return ($this == $object);
        }
    }
}
