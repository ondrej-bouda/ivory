<?php
namespace Ivory\Relation\Alg;

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
