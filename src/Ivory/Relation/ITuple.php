<?php
namespace Ivory\Relation;

interface ITuple extends \ArrayAccess, \Traversable
{
    /**
     * @return array associative array of column names to the corresponding values held by this tuple
     */
    function toArray();
}
