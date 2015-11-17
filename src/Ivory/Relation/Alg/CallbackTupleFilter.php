<?php
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleFilter extends CallbackAlg implements ITupleFilter
{
    public function accept(ITuple $tuple)
    {
        return $this->call($tuple);
    }
}
