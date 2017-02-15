<?php
namespace Ivory\Value;

/**
 * Representation of a polygon on a plane.
 *
 * The objects are immutable.
 */
class Polygon
{
    /** @var Point[] */
    private $points;


    /**
     * Creates a new polygon defined by the list of its vertices.
     *
     * @param Point[]|float[][] $points
     * @return Polygon
     */
    public static function fromPoints($points): Polygon
    {
        if (count($points) == 0) {
            throw new \InvalidArgumentException('points');
        }

        $normalized = [];
        foreach ($points as $i => $point) {
            if ($point instanceof Point) {
                $normalized[] = $point;
            } elseif (is_array($point)) {
                $normalized[] = Point::fromCoords($point[0], $point[1]);
            } else {
                throw new \InvalidArgumentException("points[$i]");
            }
        }

        return new Polygon($normalized);
    }

    private function __construct($points)
    {
        $this->points = $points;
    }

    /**
     * @return Point[] the list of vertices determining this polygon
     */
    public function getPoints()
    {
        return $this->points;
    }

    public function __toString()
    {
        return '(' . implode(',', $this->points) . ')';
    }
}
