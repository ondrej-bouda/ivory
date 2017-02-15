<?php
namespace Ivory\Type\Std;

use Ivory\Value\Line;
use Ivory\Value\LineSegment;

/**
 * Infinite line on a plane.
 *
 * Represented as a {@link \Ivory\Value\Line} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class LineType extends CompoundGeometricType
{
    /** @var LineSegmentType */
    private $lineSegType;


    public function __construct(string $schemaName, string $name, $connection)
    {
        parent::__construct($schemaName, $name, $connection);

        $this->lineSegType = new LineSegmentType($schemaName, $name . '@' . LineSegmentType::class, $connection);
    }


    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        // the { A, B, C } variant
        $re = '~^ \s*
                \{ \s*
                ([0-9.e+-]+) \s* , \s*
                ([0-9.e+-]+) \s* , \s*
                ([0-9.e+-]+) \s*
                \} \s*
                $~x';
        if (preg_match($re, $str, $m)) {
            try {
                return Line::fromEquationCoeffs($m[1], $m[2], $m[3]);
            } catch (\InvalidArgumentException $e) {
                $this->throwInvalidValue($str, $e);
            }
        }

        // given by two distinct points
        try {
            /** @var LineSegment $lineSeg */
            $lineSeg = $this->lineSegType->parseValue($str);
            return Line::fromPoints($lineSeg->getStart(), $lineSeg->getEnd());
        } catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
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
            $this->throwInvalidValue($val);
        }
    }
}
