<?php
declare(strict_types=1);
namespace Ivory\Relation\Alg;

class CallbackValueEqualizer extends CallbackAlg implements IValueEqualizer
{
    public function equal($first, $second): bool
    {
        return $this->call($first, $second);
    }
}
