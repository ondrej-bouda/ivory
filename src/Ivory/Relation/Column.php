<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;
use Ivory\Relation\Alg\CallbackValueComparator;
use Ivory\Relation\Alg\CallbackValueHasher;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Relation\Alg\IValueComparator;
use Ivory\Relation\Alg\IValueHasher;
use Ivory\Type\IType;
use Ivory\Utils\IComparable;

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

    public function renameTo(string $newName): IColumn
    {
        return new Column($this->relation, $this->colDef, $newName, $this->type);
    }

    public function bindToRelation(IRelation $relation): IColumn
    {
        return new Column($relation, $this->colDef, $this->name, $this->type);
    }

    public function filter($decider): IColumn
    {
        return new FilteredColumn($this, $decider);
    }

    public function uniq($hasher = null, $comparator = null): IColumn
    {
        if ($hasher === null) {
            $hasher = new CallbackValueHasher(function ($value) {
                return (is_int($value) || is_string($value) ? $value : serialize($value));
            });
        } elseif ($hasher === 1) {
            $hasher = new CallbackValueHasher(function ($value) {
                return 1;
            });
        } elseif (!$hasher instanceof IValueHasher) {
            $hasher = new CallbackValueHasher($hasher);
        }

        if ($comparator === null) {
            $comparator = new CallbackValueComparator(function ($a, $b) {
                if (is_object($a) && is_object($b) && $a instanceof IComparable) {
                    return $a->equals($b);
                } else {
                    return ($a == $b);
                }
            });
        } elseif (!$comparator instanceof IValueComparator) {
            $comparator = new CallbackValueComparator($comparator);
        }

        $hashTable = [];
        return new FilteredColumn($this, function ($value) use ($hasher, $comparator, &$hashTable) {
            $h = $hasher->hash($value);
            if (!isset($hashTable[$h])) {
                $hashTable[$h] = [$value];
                return true;
            } else {
                foreach ($hashTable[$h] as $v) {
                    if ($comparator->equal($value, $v)) {
                        return false;
                    }
                }
                $hashTable[$h][] = $value;
                return true;
            }
        });
    }

    public function toArray(): array
    {
        return iterator_to_array($this, false);
    }

    public function value(int $valueOffset = 0)
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
