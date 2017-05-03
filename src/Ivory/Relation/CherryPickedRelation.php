<?php
namespace Ivory\Relation;

/**
 * A relation consisting of just several selected tuples of a base relation.
 */
class CherryPickedRelation extends StreamlinedRelation
{
    private $offsetMap;

    /**
     * @param IRelation $source
     * @param int[] $cherryPickedTupleOffsets list of zero-based offsets of tuples to cherry-pick from <tt>$source</tt>
     */
    public function __construct(IRelation $source, array $cherryPickedTupleOffsets = [])
    {
        parent::__construct($source);

        $this->offsetMap = $cherryPickedTupleOffsets;
    }

    /**
     * Cherry-picks an additional tuple, on top of the already cherry-picked ones.
     *
     * The tuple gets sorted at the end of this relation.
     * A single tuple may be cherry-picked multiple times.
     *
     * @param int $tupleOffset
     * @return $this
     */
    public function cherryPick(int $tupleOffset): CherryPickedRelation
    {
        $this->offsetMap[] = $tupleOffset;
        return $this;
    }

    public function count()
    {
        return count($this->offsetMap);
    }

    public function tuple(int $offset = 0): ITuple
    {
        $effectiveOffset = ($offset >= 0 ? $offset : $this->count() + $offset);
        if (isset($this->offsetMap[$effectiveOffset])) {
            $sourceOffset = $this->offsetMap[$effectiveOffset];
            return parent::tuple($sourceOffset);
        } else {
            throw new \OutOfBoundsException("Tuple offset $offset out of the relation bounds");
        }
    }

    public function getIterator()
    {
        return new RelationSeekableIterator($this);
    }
}
