<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Representation of a rectangular box on a plane.
 *
 * The objects are immutable.
 */
final class Box
{
    /** @var Point */
    private $upperRight;
    /** @var Point */
    private $lowerLeft;


    /**
     * Creates a new box from a corner and its opposite corner, each as either a {@link Point} or a pair of coordinates.
     *
     * @param Point|float[] $corner
     * @param Point|float[] $oppositeCorner
     * @return Box
     */
    public static function fromOppositeCorners($corner, $oppositeCorner): Box
    {
        if (is_array($corner)) {
            $corner = Point::fromCoords($corner);
        } elseif (!$corner instanceof Point) {
            throw new \InvalidArgumentException('corner');
        }

        if (is_array($oppositeCorner)) {
            $oppositeCorner = Point::fromCoords($oppositeCorner);
        } elseif (!$oppositeCorner instanceof Point) {
            throw new \InvalidArgumentException('oppositeCorner');
        }

        $upperRight = Point::fromCoords(
            max($corner->getX(), $oppositeCorner->getX()),
            max($corner->getY(), $oppositeCorner->getY())
        );

        $lowerLeft = Point::fromCoords(
            min($corner->getX(), $oppositeCorner->getX()),
            min($corner->getY(), $oppositeCorner->getY())
        );

        return new Box($upperRight, $lowerLeft);
    }


    private function __construct(Point $upperRight, Point $lowerLeft)
    {
        $this->upperRight = $upperRight;
        $this->lowerLeft = $lowerLeft;
    }

    /**
     * @return Point upper right corner of the box
     */
    public function getUpperRight(): Point
    {
        return $this->upperRight;
    }

    /**
     * @return Point upper left corner of the box
     */
    public function getUpperLeft(): Point
    {
        return Point::fromCoords($this->lowerLeft->getX(), $this->upperRight->getY());
    }

    /**
     * @return Point lower right corner of the box
     */
    public function getLowerRight(): Point
    {
        return Point::fromCoords($this->upperRight->getX(), $this->lowerLeft->getY());
    }

    /**
     * @return Point lower left corner of the box
     */
    public function getLowerLeft(): Point
    {
        return $this->lowerLeft;
    }

    public function getLeftSide(): LineSegment
    {
        return LineSegment::fromEndpoints($this->getUpperLeft(), $this->lowerLeft);
    }

    public function getRightSide(): LineSegment
    {
        return LineSegment::fromEndpoints($this->getUpperRight(), $this->getLowerRight());
    }

    public function getUpperSide(): LineSegment
    {
        return LineSegment::fromEndpoints($this->getUpperLeft(), $this->getUpperRight());
    }

    public function getLowerSide(): LineSegment
    {
        return LineSegment::fromEndpoints($this->getLowerLeft(), $this->getLowerRight());
    }

    public function getArea()
    {
        return abs(
            ($this->lowerLeft->getX() - $this->upperRight->getX())
            *
            ($this->lowerLeft->getY() - $this->upperRight->getY())
        );
    }

    public function __toString()
    {
        return "({$this->upperRight}, {$this->lowerLeft})";
    }
}
