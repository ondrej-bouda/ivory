<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Exception\IncomparableException;

/**
 * Specifies that the implementing object can compare itself to other values, making a total order.
 */
interface IComparable extends IEqualable
{
    /**
     * Compares this object to another value.
     *
     * Note this method must be consistent with {@link equals()}, i.e., it must return 0 if and only if {@link equals()}
     * returns <tt>true</tt>.
     *
     * @param mixed $other
     * @return int the comparison result: negative integer, zero, or positive integer if <tt>$this</tt> is less than,
     *               equal to, or greater than <tt>$other</tt>, respectively
     * @throws IncomparableException if the values are incomparable
     * @throws \InvalidArgumentException if <tt>$other</tt> is <tt>null</tt>
     */
    function compareTo($other): int;
}
