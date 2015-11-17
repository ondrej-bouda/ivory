<?php
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleHasher extends CallbackAlg implements ITupleHasher
{
    public function hash(ITuple $tuple)
    {
        return $this->call($tuple);
    }
}
