<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Exception\IncomparableException;

/**
 * Provides an implementation of the {@link IComparable} interface, comparing objects using the PHP `<=>` operator.
 *
 * Only objects of the exact same class are considered comparable. Other types of values emit an
 * {@link IncomparableException}.
 */
trait ComparableWithPhpOperators
{
    use EqualableWithPhpOperators;

    final public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException('comparing with null');
        } elseif (get_class($this) == get_class($other)) {
            return ($this <=> $other);
        } else {
            throw new IncomparableException();
        }
    }
}
