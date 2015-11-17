<?php
namespace Ivory\Relation\Alg;

interface IValueHasher
{
    /**
     * @param mixed $value
     * @return string|int hash of <tt>$value</tt>
     */
    function hash($value);
}