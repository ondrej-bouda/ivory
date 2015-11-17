<?php
namespace Ivory\Relation\Alg;

use Iterator;

class TupleFilterIterator extends \FilterIterator
{
    private $decider;

    public function __construct(Iterator $iterator, ITupleFilter $decider)
    {
        parent::__construct($iterator);

        $this->decider = $decider;
    }

    public function accept()
    {
        return $this->decider->accept($this->current());
    }
}
