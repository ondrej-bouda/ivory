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

    public function accept(): bool
    {
        return $this->decider->accept($this->current());
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function key()
    {
        return $this->k;
    }

    public function rewind(): void
    {
        parent::rewind();
        $this->k = 0;
    }

    public function next(): void
    {
        parent::next();
        $this->k++;
    }
}
