<?php
namespace Ivory\Type;

trait TotallyOrderedByPhpOperators
{
    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }

        return $a <=> $b;
    }
}
