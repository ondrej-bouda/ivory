<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Relation\ITuple;

interface ITupleEvaluator
{
    /**
     * Computes a value for a given tuple.
     *
     * @param ITuple $tuple
     * @return mixed
     */
    function evaluate(ITuple $tuple);
}
