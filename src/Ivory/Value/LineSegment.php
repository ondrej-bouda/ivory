<?php
namespace Ivory\Value;

/**
 * Representation of a finite line segment on a plane.
 *
 * The objects are immutable.
 */
class LineSegment
{
    /** @var Point */
    private $start;
    /** @var Point */
    private $end;


    /**
     * Creates a new line segment from its start and end point, each as either a {@link Point} or a pair of coordinates.
     *
     * @param Point|float[] $start
     * @param Point|float[] $end
     * @return LineSegment
     */
    public static function fromEndpoints($start, $end)
    {
        if (is_array($start)) {
            $start = Point::fromCoords($start);
        } elseif (!$start instanceof Point) {
            throw new \InvalidArgumentException('start');
        }

        if (is_array($end)) {
            $end = Point::fromCoords($end);
        } elseif (!$end instanceof Point) {
            throw new \InvalidArgumentException('end');
        }

        return new LineSegment($start, $end);
    }


    private function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * @return Point the first endpoint of this line segment
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @return Point the second endpoint of this line segment
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @return float length of the line segment
     */
    public function getLength()
    {
        return sqrt(
            ($this->start->getX() - $this->end->getX()) ** 2
            +
            ($this->start->getY() - $this->end->getY()) ** 2
        );
    }

    public function __toString()
    {
        return "[{$this->start}, {$this->end}]";
    }
}
