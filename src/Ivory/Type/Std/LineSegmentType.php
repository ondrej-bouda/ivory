<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\LineSegment;

/**
 * Finite line segment on a plane.
 *
 * Represented as a {@link \Ivory\Value\LineSegment} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-geometric.html
 */
class LineSegmentType extends CompoundGeometricType
{
    public function parseValue(string $extRepr)
    {
        $re = '~^ \s*
                ((\()|(\[))? \s*                    # optional opening parenthesis or bracket
                ((?(1)\()[^,]+,[^,]+(?(1)\))) \s*   # the first endpoint; parenthesized if the whole segment is parenthesized
                , \s*
                ((?(1)\().+(?(1)\))) \s*            # the second endpoint; parenthesized if the whole segment is parenthesized
                (?(2)\)|(?(3)\])) \s*               # pairing closing parenthesis or bracket
                $~x';

        if (preg_match($re, $extRepr, $m)) {
            try {
                $start = $this->pointType->parseValue($m[4]);
                $end = $this->pointType->parseValue($m[5]);
                return LineSegment::fromEndpoints($start, $end);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($extRepr, $e);
            }
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val instanceof LineSegment) {
            return sprintf('lseg(%s,%s)',
                $this->pointType->serializeValue($val->getStart()),
                $this->pointType->serializeValue($val->getEnd())
            );
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
