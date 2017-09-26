<?php
declare(strict_types=1);

namespace Ivory\Value;

/**
 * Representation of a path on a plane.
 *
 * The path is composed from a continuous series of line segments. A path can either be open or closed - an open path
 * ends at its last point, whereas a closed path has an extra (implicit) line segment between the last and the first
 * point.
 *
 * The objects are immutable.
 */
class Path
{
    /** Denotes an open path, i.e., that which ends in its last vertex. */
    const OPEN = true;
    /** Denotes a closed path, i.e., that which implicitly contains a line segment between its last and first vertex. */
    const CLOSED = false;

    /** @var Point[] */
    private $points;
    /** @var bool */
    private $open;


    /**
     * Creates a new path defined by the list of its vertices.
     *
     * @param Point[]|float[][] $points
     * @param bool $isOpen whether the path is open or closed; {@link Path::OPEN} and {@link Path::CLOSED} may be used
     * @return Path
     */
    public static function fromPoints(array $points, bool $isOpen = self::CLOSED): Path
    {
        if (count($points) == 0) {
            throw new \InvalidArgumentException('points');
        }
        if (!is_bool($isOpen)) {
            $isOpen = (bool)$isOpen;
            trigger_error(
                sprintf('%s: $isOpen shall be one of %s::OPEN or %s::CLOSED, or just a boolean. %s::%s is considered.',
                    __METHOD__, __CLASS__, __CLASS__, __CLASS__, ($isOpen ? 'OPEN' : 'CLOSED')
                ),
                E_USER_NOTICE
            );
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

        return new Path($normalized, $isOpen);
    }

    private function __construct(array $points, bool $isOpen)
    {
        $this->points = $points;
        $this->open = $isOpen;
    }

    /**
     * @return Point[] the list of vertices determining this path
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @return bool whether the path is open ({@link Path::OPEN}) or closed ({@link Path::CLOSED})
     */
    public function isOpen(): bool
    {
        return $this->open;
    }

    public function __toString()
    {
        return ($this->isOpen() ? '[' : '(') . implode(',', $this->points) . ($this->isOpen() ? ']' : ')');
    }
}
