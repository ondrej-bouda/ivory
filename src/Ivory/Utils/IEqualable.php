<?php
declare(strict_types=1);
namespace Ivory\Utils;

/**
 * Defines logical equality on objects of the implementing class.
 */
interface IEqualable
{
    /**
     * @param mixed $other
     * @return bool whether <tt>$this</tt> and <tt>$other</tt> are equal
     */
    function equals($other): bool;
}
