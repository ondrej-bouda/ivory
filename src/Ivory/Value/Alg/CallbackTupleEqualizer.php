<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Relation\ITuple;

class CallbackTupleEqualizer extends CallbackAlg implements ITupleEqualizer
{
    public function equal(ITuple $first, ITuple $second): bool
    {
        return $this->call($first, $second);
    }
}
