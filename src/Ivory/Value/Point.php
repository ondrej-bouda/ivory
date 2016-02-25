<?php
namespace Ivory\Value;

/**
 * Representation of a point on a plane.
 *
 * The objects are immutable.
 */
class Point
{
    private $x;
    private $y;


    /**
     * Creates a new point from its X- and Y-coordinates, either specified as two arguments or as a two-item array.
     *
     * @param float|float[] $x
     * @param float|null $y
     * @return Point
     */
    public static function fromCoords($x, $y = null)
    {
        if ($y === null && is_array($x) && count($x) == 2) {
            $y = $x[1];
            $x = $x[0];
        }

        if (!is_numeric($x)) {
            throw new \InvalidArgumentException('x');
        }
        if (!is_numeric($y)) {
            throw new \InvalidArgumentException('y');
        }

        return new Point((float)$x, (float)$y);
    }


    private function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @return float the X-coordinate of the point
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * @return float the Y-coordinate of the point
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * @return float[] pair of the X- and Y-coordinate of the point
     */
    public function toCoords()
    {
        return [$this->x, $this->y];
    }

    public function __toString()
    {
        return "({$this->x},{$this->y})";
    }
}
