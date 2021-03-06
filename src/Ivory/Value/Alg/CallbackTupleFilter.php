<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleFilter extends CallbackAlg implements ITupleFilter
{
    public function accept(ITuple $tuple): bool
    {
        return $this->call($tuple);
    }
}
