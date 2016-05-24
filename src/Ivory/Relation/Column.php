<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Type\IType;

class Column implements \IteratorAggregate, IColumn
{
    private $relation;
    private $colDef;
    private $name;
    private $type;


    /**
     * @param IRelation $relation relation the column is a part of
     * @param int|string|ITupleEvaluator|\Closure $colDef offset or tuple evaluator of the column within the relation
     * @param string|null $name name of the column, or <tt>null</tt> if not named
     * @param IType|null $type type of the column values
     */
    public function __construct(IRelation $relation, $colDef, $name, $type)
    {
        $this->relation = $relation;
        $this->colDef = $colDef;
        $this->name = $name;
        $this->type = $type;
    }


    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function renameTo($newName)
    {
        return new Column($this->relation, $this->colDef, $newName, $this->type);
    }

    public function filter($decider)
    {
        return new FilteredColumn($this, $decider);
    }

    public function uniq($hasher = null, $comparator = null)
    {
        throw new NotImplementedException();
    }

    public function toArray()
    {
        $result = [];
        foreach ($this as $value) {
            $result[] = $value;
        }
        return $result;
    }

    public function value($valueOffset = 0)
    {
        return $this->relation->value($this->colDef, $valueOffset);
    }

    //region ICachingDataProcessor

    public function populate()
    {
        throw new NotImplementedException();
    }

    public function flush()
    {
        throw new NotImplementedException();
    }

    //endregion

    //region Countable

    public function count()
    {
        return $this->relation->count();
    }

    //endregion

    //region IteratorAggregate

    public function getIterator()
    {
        return new ColumnIterator($this);
    }

    //endregion
}
