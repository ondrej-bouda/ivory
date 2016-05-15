<?php
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;

/**
 * Standard implementations of IRelation operations which are defined solely in terms of other operations.
 */
trait RelationMacros
{
    abstract public function project($columns);

    /**
     * @param int $offset zero-based offset of the tuple to get
     * @return ITuple the `$offset`-th tuple of this relation
     * @throws \OutOfBoundsException when this relation has fewer than `$offset+1` tuples
     */
    abstract public function tuple($offset = 0);


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

}
