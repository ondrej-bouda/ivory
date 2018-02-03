<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

class TupleFilterIterator extends \FilterIterator
{
    private $decider;
    private $k = 0;

    public function __construct(\Iterator $iterator, ITupleFilter $decider)
    {
        parent::__construct($iterator);

        $this->decider = $decider;
    }

    public function accept()
    {
        return $this->decider->accept($this->current());
    }

    public function key()
    {
        return $this->k;
    }

    public function rewind()
    {
        parent::rewind();
        $this->k = 0;
    }

    public function next()
    {
        parent::next();
        $this->k++;
    }
}
