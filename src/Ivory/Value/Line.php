<?php
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
     * Creates a new line according to the coefficients in the <tt>Ax + By + C = 0</tt> describing it.
     *
     * @param float $a the coefficient A
     * @param float $b the coefficient B
     * @param float $c the coefficient C
     * @return Line
     */
    public static function fromEquationCoeffs($a, $b, $c)
    {
        if ($a == 0 && $b == 0) {
            throw new \InvalidArgumentException('$a and $b cannot both be zero');
        }

        $points = [
            ($a != 0 ? Point::fromCoords(-$c / $a, 0) : Point::fromCoords(1, -$c / $b)), // $a == 0: parallel with x-axis
            ($b != 0 ? Point::fromCoords(0, -$c / $b) : Point::fromCoords(-$c / $a, 1)), // $b == 0: parallel with y-axis
        ];
        return new Line($a, $b, $c, $points);
    }

    /**
     * Creates a new line defined by two different points.
     *
     * @param Point|float $p1
     * @param Point|float $p2
     * @return Line
     */
    public static function fromPoints($p1, $p2)
    {
        if ($p1 == $p2) {
            throw new \InvalidArgumentException('$p1 and $p2 must be different for determining a line');
        }

        $a = $p1->getY() - $p2->getY();
        $b = $p2->getX() - $p1->getX();
        $c = ($p1->getX() - $p2->getX()) * $p1->getY() - ($p1->getY() - $p2->getY()) * $p1->getX();

        return new Line($a, $b, $c, [$p1, $p2]);
    }

    private function __construct($a, $b, $c, $points)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
        $this->points = $points;
    }

    /**
     * Returns coefficients A, B, and C in the linear equation <tt>Ax + By + C = 0</tt> describing this line.
     *
     * @return float[] the triple of A, B, and C under indices 0, 1, and 2, and also under indices 'a', 'b', and 'c'
     */
    public function getEquationCoeffs()
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
    public function getPoints()
    {
        return $this->points;
    }

    public function __toString()
    {
        return "\{{$this->a}x + {$this->b}y + {$this->c} = 0\}";
    }
}
