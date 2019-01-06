<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Representation of a PostgreSQL transaction ID snapshot.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/functions-info.html#FUNCTIONS-TXID-SNAPSHOT
 */
class TxIdSnapshot
{
    private $xmin;
    private $xmax;
    private $xipList;

    /**
     * @param int $xmin earliest transaction ID that is still active;
     *                  must be a positive integer
     * @param int $xmax first as-yet-unassigned transaction ID;
     *                  must be a positive integer greater or equal to <tt>$xmin</tt>
     * @param int[] $xipList IDs of transactions active at the time of the snapshot;
     *                       each item must be between <tt>$xmin</tt> (inclusive) and <tt>$xmax</tt> (exclusive)
     * @return TxIdSnapshot
     * @throws \InvalidArgumentException when <tt>$xmin</tt> or <tt>$xmax</tt> have an invalid value
     * @throws \OutOfRangeException when any <tt>$xipList</tt> item is out of <tt>[$xmin, $xmax)</tt>
     */
    public static function fromParts(int $xmin, int $xmax, array $xipList): TxIdSnapshot
    {
        if ($xmin <= 0 || $xmax <= 0 || $xmin > $xmax) {
            throw new \InvalidArgumentException('invalid $xmin or $xmax');
        }
        if ($xipList) {
            sort($xipList);
            if ($xipList[0] < $xmin || $xipList[count($xipList) - 1] >= $xmax) {
                throw new \OutOfRangeException('$xipList items out of [$xmin,$xmax)');
            }
        }
        return new TxIdSnapshot($xmin, $xmax, $xipList);
    }

    /**
     * @param string $str transaction ID snapshot written as <tt>$xmin:$xmax:[$xipList]</tt>
     * @return TxIdSnapshot
     * @throws \InvalidArgumentException on invalid format or when <tt>$xmin</tt> or <tt>$xmax</tt> have an invalid
     *                                     value
     * @throws \OutOfRangeException when any <tt>$xipList</tt> item is out of <tt>[$xmin, $xmax)</tt>
     */
    public static function fromString(string $str): TxIdSnapshot
    {
        $parts = explode(':', $str);
        if (count($parts) != 3) {
            throw new \InvalidArgumentException('invalid format, expecting $xmin:$xmax:[$xipList]');
        }
        $xipList = ($parts[2] ? explode(',', $parts[2]) : []);
        return self::fromParts((int)$parts[0], (int)$parts[1], $xipList);
    }

    private function __construct(int $xmin, int $xmax, array $xipList)
    {
        $this->xmin = $xmin;
        $this->xmax = $xmax;
        $this->xipList = $xipList;
    }

    public function getXmin(): int
    {
        return $this->xmin;
    }

    public function getXmax(): int
    {
        return $this->xmax;
    }

    /**
     * @return int[]
     */
    public function getXipList(): array
    {
        return $this->xipList;
    }

    public function toString(): string
    {
        return sprintf('%d:%d:%s', $this->xmin, $this->xmax, implode(',', $this->xipList));
    }
}
