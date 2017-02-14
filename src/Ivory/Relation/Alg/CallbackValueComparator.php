<?php
namespace Ivory\Relation\Alg;

class CallbackValueComparator extends CallbackAlg implements IValueComparator
{
    public function equal($first, $second): bool
    {
        return $this->call($first, $second);
    }
}
