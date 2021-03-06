<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Type\IValueSerializer;
use Ivory\Value\Alg\CallbackValueFilter;
use Ivory\Value\Alg\IValueFilter;

class FilteredColumn implements \IteratorAggregate, IColumn, ICachingDataProcessor
{
    private $baseCol;
    private $decider;
    private $data = null;

    /**
     * @param IColumn $baseCol
     * @param IValueFilter|callable $decider
     */
    public function __construct(IColumn $baseCol, $decider)
    {
        $this->baseCol = $baseCol;
        $this->decider = ($decider instanceof IValueFilter ? $decider : new CallbackValueFilter($decider));
    }


    //region ICachingDataProcessor

    public function populate(): void
    {
        if ($this->data === null) {
            $this->data = [];
            foreach ($this->baseCol as $value) {
                if ($this->decider->accept($value)) {
                    $this->data[] = $value;
                }
            }
        }
    }

    public function flush(): void
    {
        $this->data = null;
    }

    //endregion

    //region IColumn

    public function getName(): ?string
    {
        return $this->baseCol->getName();
    }

    public function getType(): ?IValueSerializer
    {
        return $this->baseCol->getType();
    }

    public function renameTo(string $newName): IColumn
    {
        return new FilteredColumn($this->baseCol->renameTo($newName), $this->decider);
    }

    public function bindToRelation(IRelation $relation): IColumn
    {
        return new FilteredColumn($this->baseCol->bindToRelation($relation), $this->decider);
    }

    public function filter($decider): IColumn
    {
        return new FilteredColumn($this, $decider);
    }

    public function uniq($hasher = null, $equalizer = null): IColumn
    {
        return new FilteredColumn($this->baseCol->uniq($hasher, $equalizer), $this->decider);
    }

    public function toArray(): array
    {
        $this->populate();
        return $this->data;
    }

    public function value(int $valueOffset = 0)
    {
        $this->populate();
        $cnt = count($this->data);

        if ($valueOffset >= 0) {
            if ($valueOffset < $cnt) {
                return $this->data[$valueOffset];
            } else {
                throw new \OutOfBoundsException("The column does not have offset $valueOffset");
            }
        } else {
            if (-$valueOffset <= $cnt) {
                return $this->data[$valueOffset + $cnt];
            } else {
                throw new \OutOfBoundsException("The column does not have offset $valueOffset");
            }
        }
    }

    //endregion

    //region \Countable

    public function count(): int
    {
        $this->populate();
        return count($this->data);
    }

    //endregion

    //region \IteratorAggregate

    public function getIterator(): \Traversable
    {
        $this->populate();
        return new \ArrayIterator($this->data);
    }

    //endregion
}
