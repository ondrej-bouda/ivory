<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Value\Alg\ComparableWithPhpOperators;
use Ivory\Value\Alg\IComparable;

/**
 * Representation of a PostgreSQL tuple ID.
 *
 * A tuple ID identifies the physical location of the row within its table.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class TupleId implements IComparable
{
    use ComparableWithPhpOperators;

    private $blockNumber;
    private $tupleIndex;

    /**
     * @param int $blockNumber number of block within which the row is stored
     * @param int $tupleIndex index with the block where the row is stored
     * @return TupleId
     */
    public static function fromCoordinates(int $blockNumber, int $tupleIndex): TupleId
    {
        return new TupleId($blockNumber, $tupleIndex);
    }

    private function __construct(int $blockNumber, int $tupleIndex)
    {
        $this->blockNumber = $blockNumber;
        $this->tupleIndex = $tupleIndex;
    }

    /**
     * @return int number of block within which the row is stored
     */
    public function getBlockNumber(): int
    {
        return $this->blockNumber;
    }

    /**
     * @return int index with the block where the row is stored
     */
    public function getTupleIndex(): int
    {
        return $this->tupleIndex;
    }
}
