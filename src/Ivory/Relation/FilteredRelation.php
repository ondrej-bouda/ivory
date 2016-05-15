<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\CallbackTupleFilter;
use Ivory\Relation\Alg\ITupleFilter;
use Ivory\Relation\Alg\TupleFilterIterator;

class FilteredRelation extends StreamlinedRelation
{
    private $decider;

    /**
     * @param IRelation $source
     * @param ITupleFilter|\Closure $decider
     */
    public function __construct($source, $decider)
    {
        parent::__construct($source);

        if ($decider instanceof \Closure) {
            $this->decider = new CallbackTupleFilter($decider);
        }
        elseif ($decider instanceof ITupleFilter) {
            $this->decider = $decider;
        }
        else {
            throw new \InvalidArgumentException('$decider');
        }
    }

    public function getIterator()
    {
        $src = parent::getIterator();
        if (!$src instanceof \Iterator && $src instanceof \Traversable) {
            $src = new \IteratorIterator($src);
        }
        return new TupleFilterIterator($src, $this->decider);
    }
}
