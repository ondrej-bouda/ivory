<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Value\Alg\CallbackTupleFilter;
use Ivory\Value\Alg\ITupleFilter;
use Ivory\Value\Alg\TupleFilterIterator;

class FilteredRelation extends StreamlinedRelation implements ICachingDataProcessor
{
    private $decider;
    private $acceptMap = null;

    /**
     * @param IRelation $source
     * @param ITupleFilter|\Closure $decider
     */
    public function __construct(IRelation $source, $decider)
    {
        parent::__construct($source);

        if ($decider instanceof \Closure) {
            $this->decider = new CallbackTupleFilter($decider);
        } elseif ($decider instanceof ITupleFilter) {
            $this->decider = $decider;
        } else {
            throw new \InvalidArgumentException('$decider');
        }
    }

    public function populate(): void
    {
        if ($this->acceptMap !== null) {
            return;
        }

        $this->acceptMap = [];
        foreach ($this->source as $i => $tuple) {
            if ($this->decider->accept($tuple)) {
                $this->acceptMap[] = $i;
            }
        }
    }

    public function flush(): void
    {
        $this->acceptMap = null;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function count(): int
    {
        $this->populate();
        return count($this->acceptMap);
    }

    public function tuple(int $offset = 0): ITuple
    {
        $this->populate();
        $effectiveOffset = ($offset >= 0 ? $offset : $this->count() + $offset);
        if (isset($this->acceptMap[$effectiveOffset])) {
            return parent::tuple($this->acceptMap[$effectiveOffset]);
        } else {
            throw new \OutOfBoundsException("Tuple offset $offset out of the relation bounds");
        }
    }

    public function getIterator(): \Traversable
    {
        $src = parent::getIterator();
        if (!$src instanceof \Iterator && $src instanceof \Traversable) {
            $src = new \IteratorIterator($src);
        }
        return new TupleFilterIterator($src, $this->decider);
    }
}
