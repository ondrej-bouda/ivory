<?php
namespace Ivory\Value;

/**
 * Representation of a PostgreSQL transaction ID snapshot.
 *
 * The objects are immutable.
 *
 * @see http://www.postgresql.org/docs/9.4/static/functions-info.html#FUNCTIONS-TXID-SNAPSHOT
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
    public static function fromParts($xmin, $xmax, array $xipList)
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
    public static function fromString($str)
    {
        $parts = explode(':', $str);
        if (count($parts) != 3) {
            throw new \InvalidArgumentException('invalid format, expecting $xmin:$xmax:[$xipList]');
        }
        $xipList = ($parts[2] ? explode(',', $parts[2]) : []);
        return self::fromParts($parts[0], $parts[1], $xipList);
    }

    private function __construct($xmin, $xmax, $xipList)
    {
        $this->xmin = $xmin;
        $this->xmax = $xmax;
        $this->xipList = $xipList;
    }

    /**
     * @return int
     */
    public function getXmin()
    {
        return $this->xmin;
    }

    /**
     * @return int
     */
    public function getXmax()
    {
        return $this->xmax;
    }

    /**
     * @return int[]
     */
    public function getXipList()
    {
        return $this->xipList;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return sprintf('%d:%d:%s', $this->xmin, $this->xmax, implode(',', $this->xipList));
    }
}
