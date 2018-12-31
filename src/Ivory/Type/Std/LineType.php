<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Line;
use Ivory\Value\LineSegment;

/**
 * Infinite line on a plane.
 *
 * Represented as a {@link \Ivory\Value\Line} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 */
class LineType extends CompoundGeometricType
{
    /** @var LineSegmentType */
    private $lineSegType;


    public function __construct(string $schemaName, string $name)
    {
        parent::__construct($schemaName, $name);

        $this->lineSegType = new LineSegmentType($schemaName, $name . '@' . LineSegmentType::class);
    }


    public function parseValue(string $extRepr)
    {
        // the { A, B, C } variant
        $re = '~^ \s*
                \{ \s*
                ([0-9.e+-]+) \s* , \s*
                ([0-9.e+-]+) \s* , \s*
                ([0-9.e+-]+) \s*
                \} \s*
                $~x';
        if (preg_match($re, $extRepr, $m)) {
            try {
                return Line::fromEquationCoeffs($m[1], $m[2], $m[3]);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($extRepr, $e);
            }
        }

        // given by two distinct points
        try {
            $lineSeg = $this->lineSegType->parseValue($extRepr);
            assert($lineSeg instanceof LineSegment);
            return Line::fromPoints($lineSeg->getStart(), $lineSeg->getEnd());
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val instanceof Line) {
            list($p1, $p2) = $val->getPoints();
            return sprintf('line(%s,%s)',
                $this->pointType->serializeValue($p1),
                $this->pointType->serializeValue($p2)
            );
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
