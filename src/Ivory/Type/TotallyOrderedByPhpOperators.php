<?php
namespace Ivory\Type;

trait TotallyOrderedByPhpOperators
{
    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }

        // PHP 7: use the <=> operator as a shorthand
        if ($a < $b) {
            return -1;
        } elseif ($a == $b) {
            return 0;
        } else {
            return 1;
        }
    }
}
