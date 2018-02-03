<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

class BigIntSafeStepper implements IDiscreteStepper
{
    public function step(int $delta, $value)
    {
        if ($value === null) {
            throw new \InvalidArgumentException();
        }

        return bcadd($value, $delta, 0);
    }
}
