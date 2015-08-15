<?php
namespace Ivory\Value;

/**
 * Representation of a finite line segment on a plane.
 *
 * The objects are immutable.
 */
class LineSegment
{
    private $start;
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
        }
        elseif (!$start instanceof Point) {
            throw new \InvalidArgumentException('start');
        }

        if (is_array($end)) {
            $end = Point::fromCoords($end);
        }
        elseif (!$end instanceof Point) {
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

    public function __toString()
    {
        return "[{$this->start}, {$this->end}]";
    }
}
