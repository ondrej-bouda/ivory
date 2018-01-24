<?php
declare(strict_types=1);
namespace Ivory\Utils;

/**
 * Implements the {@link IEqualable::equals()} using the {@link IComparable::compareTo()} method.
 */
trait EqualableWithCompareTo
{
    abstract public function compareTo($other): ?int;

    public function equals($other): bool
    {
        if ($other === null) {
            return false;
        }

        return ($this->compareTo($other) == 0);
    }
}
