<?php
namespace Ivory\Relation;

use Ivory\Data\Map\ArrayValueMap;
use Ivory\Data\Set\DictionarySet;
use Ivory\Data\Set\ISet;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Data\Map\ArrayTupleMap;

/**
 * Standard implementations of IRelation operations which are defined solely in terms of other operations.
 */
trait RelationMacros
{
    abstract public function project($columns);

    /**
     * @param int $offset
     * @return ITuple
     */
    abstract public function tuple($offset = 0);

    abstract function col($offsetOrNameOrEvaluator);

    public function toSet($colOffsetOrNameOrEvaluator, ISet $set = null)
    {
        if ($set === null) {
            $set = new DictionarySet();
        }

        foreach ($this->col($colOffsetOrNameOrEvaluator) as $value) {
            $set->add($value);
        }

        return $set;
    }

    final public function extend($extraColumns)
    {
        if ($extraColumns instanceof \Traversable) {
            $extraColumns = iterator_to_array($extraColumns);
        }

        return $this->project(array_merge(['*'], $extraColumns));
    }

    final public function toArray()
    {
        $result = [];
        foreach ($this as $tuple) {
            /** @var ITuple $tuple */
            $result[] = $tuple->toMap();
        }
        return $result;
    }

    final public function value($colOffsetOrNameOrEvaluator = 0, $tupleOffset = 0)
    {
        return $this->tuple($tupleOffset)->value($colOffsetOrNameOrEvaluator);
    }

    final protected function _colImpl($offsetOrNameOrEvaluator, $columns, $colNameMap, IRelation $relation)
    {
        if (is_scalar($offsetOrNameOrEvaluator)) {
            if (filter_var($offsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
                if (isset($columns[$offsetOrNameOrEvaluator])) {
                    return $columns[$offsetOrNameOrEvaluator];
                }
                else {
                    throw new UndefinedColumnException("No column at offset $offsetOrNameOrEvaluator");
                }
            }
            else {
                if (isset($colNameMap[$offsetOrNameOrEvaluator])) {
                    return $columns[$colNameMap[$offsetOrNameOrEvaluator]];
                }
                else {
                    throw new UndefinedColumnException("No column named $offsetOrNameOrEvaluator");
                }
            }
        }
        elseif ($offsetOrNameOrEvaluator instanceof ITupleEvaluator ||
            $offsetOrNameOrEvaluator instanceof \Closure)
        {
            return new Column($relation, $offsetOrNameOrEvaluator, null, null);
        }
        else {
            throw new \InvalidArgumentException('$offsetOrNameOrEvaluator');
        }
    }

    public function assoc(...$cols)
    {
        if (count($cols) < 2) {
            // TODO: consider specifying the first two arguments explicitly in IRelation - then, even IDE may check that no or only one argument is given
            throw new \InvalidArgumentException('at least 2 arguments are expected');
        }

        $multiDimKeyCols = array_slice($cols, 0, -2);
        $lastKeyCol = $cols[count($cols) - 2];
        $valueCol = $cols[count($cols) - 1];

        $map = new ArrayValueMap();
        $emptyMap = new ArrayValueMap();
        foreach ($this as $tuple) { /** @var ITuple $tuple */
            $m = $map;
            foreach ($multiDimKeyCols as $col) {
                $key = $tuple->value($col);
                $added = $m->putIfNotExists($key, $emptyMap);
                if ($added) {
                    $emptyMap = new ArrayValueMap(); // prepare a new copy for the following iterations
                }
                $m = $m[$key];
            }

            $key = $tuple->value($lastKeyCol);
            $val = $tuple->value($valueCol);
            $added = $m->putIfNotExists($key, $val);

            if (!$added) {
                $keys = [];
                foreach ($multiDimKeyCols as $col) {
                    $keys[] = $tuple->value($col);
                }
                $keys[] = $tuple->value($lastKeyCol);
                $keyDesc = implode(', ', $keys);
                trigger_error(
                    "Duplicate entry under key ($keyDesc). Skipping. Consider using multimap() instead.",
                    E_USER_WARNING
                );
            }
        }

        return $map;
    }

    public function map(...$mappingCols)
    {
        if (!$mappingCols) {
            // TODO: consider specifying at least one argument explicitly in IRelation - then, even IDE may check that no argument is given
            throw new \InvalidArgumentException('no $mappingCols');
        }

        $multiDimCols = array_slice($mappingCols, 0, -1);
        $lastCol = $mappingCols[count($mappingCols) - 1];

        // FIXME: depending on the data type of the key, either use an array-based implementation, or an object hashing implementation
        $map = new ArrayTupleMap();
        $emptyMap = new ArrayTupleMap();
        foreach ($this as $tuple) { /** @var ITuple $tuple */
            $m = $map;
            foreach ($multiDimCols as $col) {
                $key = $tuple->value($col);
                $added = $m->putIfNotExists($key, $emptyMap);
                if ($added) {
                    $emptyMap = new ArrayTupleMap();
                }
                $m = $m[$key];
            }

            $key = $tuple->value($lastCol);
            $added = $m->putIfNotExists($key, $tuple);

            if (!$added) {
                $keys = [];
                foreach ($multiDimCols as $col) {
                    $keys[] = $tuple->value($col);
                }
                $keys[] = $tuple->value($lastCol);
                $keyDesc = implode(', ', $keys);
                trigger_error(
                    "Duplicate entry under key ($keyDesc). Skipping. Consider using multimap() instead.",
                    E_USER_WARNING
                );
            }
        }

        return $map;
    }
}
