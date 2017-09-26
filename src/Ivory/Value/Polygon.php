<?php
declare(strict_types=1);

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
    public static function fromPoints(array $points): Polygon
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

    private function __construct(array $points)
    {
        $this->points = $points;
    }

    /**
     * @return Point[] the list of vertices determining this polygon
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Computes the area of the polygon.
     *
     * The algorithm taken from http://alienryderflex.com/polygon_area/
     *
     * @return float area of the polygon
     */
    public function getArea(): float
    {
        $area = 0;

        $from = $this->points[count($this->points) - 1];
        foreach ($this->points as $to) {
            $area += ($to->getX() + $from->getX()) * ($to->getY() - $from->getY());
            $from = $to;
        }

        return abs($area) / 2;
    }

    public function __toString()
    {
        return '(' . implode(',', $this->points) . ')';
    }
}
