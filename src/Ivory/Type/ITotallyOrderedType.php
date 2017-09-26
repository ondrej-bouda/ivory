<?php
namespace Ivory\Type;

use Ivory\Exception\IncomparableException;

interface ITotallyOrderedType extends IType
{
    /**
     * Compares two values of this type.
     *
     * @param mixed $a value on the left of the comparison
     * @param mixed $b value on the right of the comparison
     * @return int|null the comparison result (<0, 0, or >0 if <tt>$a < $b</tt>, <tt>$a = $b</tt>, or <tt>$a > $b</tt>,
     *                  respectively), or <tt>null</tt> if either value is <tt>null</tt>
     * @throws IncomparableException if the values are incomparable
     */
    function compareValues($a, $b): ?int;
}
