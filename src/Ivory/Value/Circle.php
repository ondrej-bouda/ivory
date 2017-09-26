<?php
declare(strict_types=1);

namespace Ivory\Value;

/**
 * Representation of a circle on a plane.
 *
 * The objects are immutable.
 */
class Circle
{
    private $center;
    private $radius;


    /**
     * Creates a new circle by its center point and radius.
     *
     * @param Point|float[] $center the center point
     * @param float $radius radius of the circle
     * @return Circle
     */
    public static function fromCoords($center, $radius): Circle
    {
        if (is_array($center)) {
            $center = Point::fromCoords($center);
        } elseif (!$center instanceof Point) {
            throw new \InvalidArgumentException('center');
        }

        if (!is_numeric($radius)) {
            throw new \InvalidArgumentException('radius');
        }

        return new Circle($center, (float)$radius);
    }


    private function __construct(Point $center, float $radius)
    {
        $this->center = $center;
        $this->radius = $radius;
    }

    /**
     * @return Point the center point of the circle
     */
    public function getCenter(): Point
    {
        return $this->center;
    }

    /**
     * @return float the radius of the circle
     */
    public function getRadius(): float
    {
        return $this->radius;
    }

    public function __toString()
    {
        return "<{$this->center};{$this->radius}>";
    }
}
