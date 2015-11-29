<?php
namespace Ivory\Relation;

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
    abstract public function tuple($offset = 0); // TODO: instead of PHPDoc, declare returning ITuple if PHP 7 may be assumed

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
}
