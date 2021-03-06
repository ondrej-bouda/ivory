<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Representation of an infinite line on a plane.
 *
 * The objects are immutable.
 */
class Line
{
    private $a;
    private $b;
    private $c;
    private $points;


    /**
     * Creates a new line according to the coefficients in the `Ax + By + C = 0` describing it.
     *
     * @param float $a the coefficient A
     * @param float $b the coefficient B
     * @param float $c the coefficient C
     * @return Line
     */
    public static function fromEquationCoeffs(float $a, float $b, float $c): Line
    {
        if ($a == 0 && $b == 0) {
            throw new \InvalidArgumentException('$a and $b cannot both be zero');
        }

        // $a == 0: parallel with x-axis; $b == 0: parallel with y-axis
        $points = [
            ($a != 0 ? Point::fromCoords(-$c / $a, 0) : Point::fromCoords(1, -$c / $b)),
            ($b != 0 ? Point::fromCoords(0, -$c / $b) : Point::fromCoords(-$c / $a, 1)),
        ];
        return new Line($a, $b, $c, $points);
    }

    /**
     * Creates a new line defined by two different points.
     *
     * @param Point $p1
     * @param Point $p2
     * @return Line
     * @throws \InvalidArgumentException if the points are the same, and thus do not determine a line
     */
    public static function fromPoints(Point $p1, Point $p2): Line
    {
        if ($p1->equals($p2)) {
            throw new \InvalidArgumentException('$p1 and $p2 must be different for determining a line');
        }

        $a = $p1->getY() - $p2->getY();
        $b = $p2->getX() - $p1->getX();
        $c = ($p1->getX() - $p2->getX()) * $p1->getY() - ($p1->getY() - $p2->getY()) * $p1->getX();

        return new Line($a, $b, $c, [$p1, $p2]);
    }

    private function __construct(float $a, float $b, float $c, array $points)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
        $this->points = $points;
    }

    /**
     * Returns coefficients A, B, and C in the linear equation `Ax + By + C = 0` describing this line.
     *
     * @return float[] the triple of A, B, and C under indices 0, 1, and 2, and also under indices 'a', 'b', and 'c'
     */
    public function getEquationCoeffs(): array
    {
        return [
            $this->a,
            $this->b,
            $this->c,
            'a' => $this->a,
            'b' => $this->b,
            'c' => $this->c,
        ];
    }

    /**
     * @return Point[] a pair of points determining the line
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function __toString()
    {
        return "\{{$this->a}x + {$this->b}y + {$this->c} = 0\}";
    }
}
