<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Data\Map\ArrayRelationMap;
use Ivory\Data\Map\ArrayValueMap;
use Ivory\Data\Map\IRelationMap;
use Ivory\Data\Map\ITupleMap;
use Ivory\Data\Map\IValueMap;
use Ivory\Data\Map\IWritableValueMap;
use Ivory\Data\Set\DictionarySet;
use Ivory\Data\Set\ISet;
use Ivory\Exception\AmbiguousException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Data\Map\ArrayTupleMap;

/**
 * Standard implementations of IRelation operations which are defined solely in terms of other operations.
 *
 * Note the trait may only be used by a class implementing {@link IRelation}.
 */
trait RelationMacros
{
    abstract public function project($columns): IRelation;

    abstract public function tuple(int $offset = 0): ITuple;

    public function col($offsetOrNameOrEvaluator): IColumn
    {
        $thisRel = $this;
        assert($thisRel instanceof IRelation);
        return $this->_colImpl($offsetOrNameOrEvaluator, $this->columns, $this->colNameMap, $thisRel);
    }


    protected static function computeColNameMap(IRelation $relation): array
    {
        $result = [];
        foreach ($relation->getColumns() as $i => $col) {
            $name = $col->getName();
            if ($name !== null && !isset($result[$name])) {
                $result[$name] = $i;
            }
        }
        return $result;
    }

    public function toSet($colOffsetOrNameOrEvaluator, ?ISet $set = null): ISet
    {
        if ($set === null) {
            $set = new DictionarySet();
        }

        foreach ($this->col($colOffsetOrNameOrEvaluator) as $value) {
            $set->add($value);
        }

        return $set;
    }

    final public function extend($extraColumns): IRelation
    {
        if ($extraColumns instanceof \Traversable) {
            $extraColumns = iterator_to_array($extraColumns);
        }

        return $this->project(array_merge(['*'], $extraColumns));
    }

    final public function toArray(): array
    {
        $result = [];
        foreach ($this as $tuple) { /** @var ITuple $tuple */
            $result[] = $tuple->toMap();
        }
        return $result;
    }

    final public function value($colOffsetOrNameOrEvaluator = 0, int $tupleOffset = 0)
    {
        return $this->tuple($tupleOffset)->value($colOffsetOrNameOrEvaluator);
    }

    final protected function _colImpl($offsetOrNameOrEvaluator, $columns, $colNameMap, IRelation $relation): IColumn
    {
        if (is_scalar($offsetOrNameOrEvaluator)) {
            if (filter_var($offsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
                if (isset($columns[$offsetOrNameOrEvaluator])) {
                    return $columns[$offsetOrNameOrEvaluator];
                } else {
                    throw new UndefinedColumnException("No column at offset $offsetOrNameOrEvaluator");
                }
            } else {
                if (!isset($colNameMap[$offsetOrNameOrEvaluator])) {
                    throw new UndefinedColumnException("No column named $offsetOrNameOrEvaluator");
                } elseif ($colNameMap[$offsetOrNameOrEvaluator] === Tuple::AMBIGUOUS_COL) {
                    throw new AmbiguousException("Multiple columns named $offsetOrNameOrEvaluator");
                } else {
                    return $columns[$colNameMap[$offsetOrNameOrEvaluator]];
                }
            }
        } elseif (
            $offsetOrNameOrEvaluator instanceof ITupleEvaluator ||
            $offsetOrNameOrEvaluator instanceof \Closure
        ) {
            return new Column($relation, $offsetOrNameOrEvaluator, null, null);
        } else {
            throw new \InvalidArgumentException('$offsetOrNameOrEvaluator');
        }
    }

    public function assoc($col1, $col2, ...$moreCols): IValueMap
    {
        return $this->assocImpl(
            function () {
                // FIXME: depending on the data type of the keys, either use an array-based implementation, or an object hashing implementation
                return new ArrayValueMap();
            },
            true, array_merge([$col1, $col2], $moreCols)
        );
    }

    public function map($mappingCol, ...$moreMappingCols): ITupleMap
    {
        return $this->assocImpl(
            function () {
                // FIXME: depending on the data type of the keys, either use an array-based implementation, or an object hashing implementation
                return new ArrayTupleMap();
            },
            false, array_merge([$mappingCol], $moreMappingCols)
        );
    }

    public function multimap($mappingCol, ...$moreMappingCols): IRelationMap
    {
        $mappingCols = array_merge([$mappingCol], $moreMappingCols);
        $multiDimKeyCols = array_slice($mappingCols, 0, -1);
        $lastKeyCol = $mappingCols[count($mappingCols) - 1];

        // FIXME: depending on the data type of the keys, either use an array-based implementation, or an object hashing implementation
        $map = new ArrayRelationMap();
        $emptyMap = new ArrayRelationMap();
        foreach ($this as $tupleOffset => $tuple) { /** @var ITuple $tuple */
            $m = $map;
            foreach ($multiDimKeyCols as $col) {
                $key = $tuple->value($col);
                $added = $m->putIfNotExists($key, $emptyMap);
                if ($added) {
                    $emptyMap = new ArrayRelationMap(); // prepare a new copy for the following iterations
                }
                $m = $m[$key];
            }

            $key = $tuple->value($lastKeyCol);
            $rel = $m->maybe($key);
            if ($rel === null) {
                $thisRel = $this;
                assert($thisRel instanceof IRelation);
                $rel = new CherryPickedRelation($thisRel, []);
                $m->put($key, $rel);
            }
            $rel->cherryPick($tupleOffset);
        }

        return $map;
    }

    /**
     * @param \Closure $emptyMapFactory closure creating an empty map for storing the data
     * @param bool $lastForValues whether the last of <tt>$cols</tt> shall be used for mapped values
     * @param array $cols
     * @return IWritableValueMap|ITupleMap
     */
    private function assocImpl(\Closure $emptyMapFactory, bool $lastForValues, array $cols)
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

    //region \IteratorAggregate

    public function getIterator()
    {
        $thisRel = $this;
        assert($thisRel instanceof IRelation);
        return new RelationSeekableIterator($thisRel);
    }

    //endregion
}
