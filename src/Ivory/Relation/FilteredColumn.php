<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\CallbackValueFilter;
use Ivory\Relation\Alg\IValueFilter;

class FilteredColumn implements \IteratorAggregate, IColumn
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

    public function populate()
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

    public function flush()
    {
        $this->data = null;
    }

    //endregion

    //region IColumn

    public function getName()
    {
        return $this->baseCol->getName();
    }

    public function getType()
    {
        return $this->baseCol->getType();
    }

    public function renameTo($newName)
    {
        return new FilteredColumn($this->baseCol->renameTo($newName), $this->decider);
    }

    public function bindToRelation(IRelation $relation)
    {
        return new FilteredColumn($this->baseCol->bindToRelation($relation), $this->decider);
    }

    public function filter($decider)
    {
        return new FilteredColumn($this, $decider);
    }

    public function uniq($hasher = null, $comparator = null)
    {
        return new FilteredColumn($this->baseCol->uniq($hasher, $comparator), $this->decider);
    }

    public function toArray()
    {
        $this->populate();
        return $this->data;
    }

    public function value($valueOffset = 0)
    {
        $this->populate();
        $cnt = count($this->data);

        if ($valueOffset >= 0) {
            if ($valueOffset < $cnt) {
                return $this->data[$valueOffset];
            }
            else {
                throw new \OutOfBoundsException("The column does not have offset $valueOffset");
            }
        }
        else {
            if (-$valueOffset <= $cnt) {
                return $this->data[$valueOffset + $cnt];
            }
            else {
                throw new \OutOfBoundsException("The column does not have offset $valueOffset");
            }
        }
    }

    //endregion

    //region \Countable

    public function count()
    {
        $this->populate();
        return count($this->data);
    }

    //endregion

    //region \IteratorAggregate

    public function getIterator()
    {
        $this->populate();
        return new \ArrayIterator($this->data);
    }

    //endregion
}
