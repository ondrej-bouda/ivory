<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

interface IValueEqualizer
{
    /**
     * @param mixed $first
     * @param mixed $second
     * @return bool <tt>true</tt> if the <tt>$first</tt> value is equivalent to the <tt>$second</tt> value,
     *              <tt>false</tt> otherwise
     */
    function equal($first, $second): bool;
}
