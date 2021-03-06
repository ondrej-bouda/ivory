<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

interface IValueFilter
{
    /**
     * @param mixed $value value to decide
     * @return bool <tt>true</tt> if <tt>$value</tt> passes this filter, <tt>false</tt> if not
     */
    function accept($value): bool;
}
