<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleEvaluator extends CallbackAlg implements ITupleEvaluator
{
    public function evaluate(ITuple $tuple)
    {
        return $this->call($tuple);
    }
}
