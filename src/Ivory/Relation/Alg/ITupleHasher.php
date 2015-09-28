<?php
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

interface ITupleHasher
{
    /**
     * @param ITuple $tuple
     * @return string|int hash of <tt>$tuple</tt>
     */
    function hash(ITuple $tuple);
}
