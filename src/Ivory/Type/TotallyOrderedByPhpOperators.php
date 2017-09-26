<?php
declare(strict_types=1);

namespace Ivory\Type;

trait TotallyOrderedByPhpOperators
{
    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }

        return $a <=> $b;
    }
}
