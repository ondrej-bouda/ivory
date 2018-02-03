<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Exception\IncomparableException;

interface IValueComparator
{
    /**
     * Compares the given values for which is less than the other.
     *
     * @param $a
     * @param $b
     * @return int the comparison result: negative integer, zero, or positive integer if <tt>$this</tt> is less than,
     *               equal to, or greater than <tt>$other</tt>, respectively
     * @throws IncomparableException
     * @throws \InvalidArgumentException if either of argument is <tt>null</tt>
     */
    function compareValues($a, $b): int;
}
