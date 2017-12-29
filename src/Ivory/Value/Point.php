<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Utils\IEqualable;

/**
 * Representation of a point on a plane.
 *
 * The objects are immutable.
 */
final class Point implements IEqualable
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
    public static function fromCoords($x, $y = null): Point
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


    private function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @return float the X-coordinate of the point
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * @return float the Y-coordinate of the point
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * @return float[] pair of the X- and Y-coordinate of the point
     */
    public function toCoords(): array
    {
        return [$this->x, $this->y];
    }

    public function __toString()
    {
        return "({$this->x},{$this->y})";
    }

    /**
     * @param object $object
     * @return bool|null <tt>true</tt> if <tt>$this</tt> and the other <tt>$object</tt> are equal to each other,
     *                   <tt>false</tt> if they are not equal,
     *                   <tt>null</tt> iff <tt>$object</tt> is <tt>null</tt>
     */
    public function equals($object): ?bool
    {
        if ($object === null) {
            return null;
        }
        if (!$object instanceof Point) {
            return false;
        }
        return (
            $this->getX() == $object->getX()
            &&
            $this->getY() == $object->getY()
        );
    }
}
