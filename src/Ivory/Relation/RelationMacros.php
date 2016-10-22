<?php
namespace Ivory\Relation;

use Ivory\Data\Map\ArrayValueMap;
use Ivory\Data\Map\IWritableValueMap;
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

    public function assoc($col1, $col2, ...$moreCols)
    {
        return $this->assocImpl(
            function () {
                // FIXME: depending on the data type of the keys, either use an array-based implementation, or an object hashing implementation
                return new ArrayValueMap();
            },
            true, array_merge([$col1, $col2], $moreCols)
        );
    }

    public function map($mappingCol, ...$moreMappingCols)
    {
        return $this->assocImpl(
            function () {
                // FIXME: depending on the data type of the keys, either use an array-based implementation, or an object hashing implementation
                return new ArrayTupleMap();
            },
            false, array_merge([$mappingCol], $moreMappingCols)
        );
    }

    /**
     * @param \Closure $emptyMapFactory closure creating an empty map for storing the data
     * @param bool $lastForValues whether the last of <tt>$cols</tt> shall be used for mapped values
     * @param array $cols
     * @return IWritableValueMap
     */
    private function assocImpl($emptyMapFactory, $lastForValues, $cols)
    {
        $multiDimKeyCols = array_slice($cols, 0, -1 - (int)$lastForValues);
        $lastKeyCol = $cols[count($cols) - 1 - (int)$lastForValues];
        $valueCol = ($lastForValues ? $cols[count($cols) - 1] : null);

        /** @var IWritableValueMap $map */
        $map = $emptyMapFactory();
        $emptyMap = $emptyMapFactory();
        foreach ($this as $tuple) { /** @var ITuple $tuple */
            $m = $map;
            foreach ($multiDimKeyCols as $col) {
                $key = $tuple->value($col);
                $added = $m->putIfNotExists($key, $emptyMap);
                if ($added) {
                    $emptyMap = $emptyMapFactory(); // prepare a new copy for the following iterations
                }
                $m = $m[$key];
            }

            $key = $tuple->value($lastKeyCol);
            $val = ($lastForValues ? $tuple->value($valueCol) : $tuple);
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
}
