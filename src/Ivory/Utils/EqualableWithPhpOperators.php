<?php
declare(strict_types=1);
namespace Ivory\Utils;

/**
 * Provides an implementation of the {@link IEqualable} interface, comparing objects using the PHP `==` operator.
 */
trait EqualableWithPhpOperators
{
    final public function equals($object): ?bool
    {
        if ($object === null) {
            return null;
        } else {
            return ($this == $object);
        }
    }
}
