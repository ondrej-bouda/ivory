<?php
declare(strict_types=1);
namespace Ivory\Utils;

/**
 * Provides an implementation of the {@link IEqualable} interface, comparing objects using the PHP `==` operator.
 */
trait EqualableWithPhpOperators
{
    final public function equals($other): bool
    {
        return (
            $other !== null &&
            get_class($this) == get_class($other) &&
            $this == $other
        );
    }
}
