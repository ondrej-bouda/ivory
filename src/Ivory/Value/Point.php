<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\IComparable;
use Ivory\Value\Alg\ComparisonUtils;

/**
 * Representation of a point on a plane.
 *
 * The objects are immutable.
 */
final class Point implements IComparable
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

    public function equals($other): bool
    {
        if (!$other instanceof Point) {
            return false;
        }
        return (
            $this->getX() == $other->getX()
            &&
            $this->getY() == $other->getY()
        );
    }

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException('comparing with null');
        }
        if (!$other instanceof Point) {
            throw new IncomparableException();
        }

        [$thisX, $thisY] = $this->toCoords();
        [$otherX, $otherY] = $other->toCoords();

        $xCmp = ComparisonUtils::compareFloats($thisX, $otherX);
        if ($xCmp != 0) {
            return $xCmp;
        } else {
            return ComparisonUtils::compareFloats($thisY, $otherY);
        }
    }
}
