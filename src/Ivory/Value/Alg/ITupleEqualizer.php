<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use Ivory\Relation\ITuple;

interface ITupleEqualizer
{
    /**
     * @param ITuple $first
     * @param ITuple $second
     * @return bool <tt>true</tt> if the <tt>$first</tt> tuple is equivalent to the <tt>$second</tt> tuple,
     *              <tt>false</tt> otherwise
     */
    function equal(ITuple $first, ITuple $second): bool;
}
