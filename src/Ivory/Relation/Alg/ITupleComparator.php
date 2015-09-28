<?php
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

/**
 * Compares two relation tuples.
 */
interface ITupleComparator
{
    /**
     * @param ITuple $first
     * @param ITuple $second
     * @return bool <tt>true</tt> if the <tt>$first</tt> tuple is equivalent to the <tt>$second</tt> tuple,
     *              <tt>false</tt> otherwise
     */
    function equal(ITuple $first, ITuple $second);
}
