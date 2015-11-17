<?php
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleComparator extends CallbackAlg implements ITupleComparator
{
    public function equal(ITuple $first, ITuple $second)
    {
        return $this->call($first, $second);
    }
}
