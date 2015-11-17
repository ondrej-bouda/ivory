<?php
namespace Ivory\Relation\Alg;

class CallbackValueComparator extends CallbackAlg implements IValueComparator
{
    public function equal($first, $second)
    {
        return $this->call($first, $second);
    }
}
