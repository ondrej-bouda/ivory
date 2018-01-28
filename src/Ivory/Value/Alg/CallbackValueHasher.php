<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

class CallbackValueHasher extends CallbackAlg implements IValueHasher
{
    public function hash($value)
    {
        return $this->call($value);
    }
}
