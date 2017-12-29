<?php
declare(strict_types=1);
namespace Ivory\Relation\Alg;

use Ivory\Relation\ITuple;

interface ITupleFilter
{
    /**
     * @param ITuple $tuple tuple to decide
     * @return bool <tt>true</tt> if <tt>$tuple</tt> passes this filter, <tt>false</tt> if not
     */
    function accept(ITuple $tuple): bool;
}
