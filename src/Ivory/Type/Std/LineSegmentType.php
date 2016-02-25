<?php
namespace Ivory\Type\Std;

use Ivory\Value\LineSegment;

/**
 * Finite line segment on a plane.
 *
 * Represented as a {@link \Ivory\Value\LineSegment} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class LineSegmentType extends CompoundGeometricType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $re = '~^ \s*
                ((\()|(\[))? \s*                    # optional opening parenthesis or bracket
                ((?(1)\()[^,]+,[^,]+(?(1)\))) \s*   # the first endpoint; parenthesized if the whole segment is parenthesized
                , \s*
                ((?(1)\().+(?(1)\))) \s*            # the second endpoint; parenthesized if the whole segment is parenthesized
                (?(2)\)|(?(3)\])) \s*               # pairing closing parenthesis or bracket
                $~x';

        if (preg_match($re, $str, $m)) {
            try {
                $start = $this->pointType->parseValue($m[4]);
                $end = $this->pointType->parseValue($m[5]);
                return LineSegment::fromEndpoints($start, $end);
            }
            catch (\InvalidArgumentException $e) {
                $this->throwInvalidValue($str, $e);
            }
        }
        else {
            $this->throwInvalidValue($str);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        elseif ($val instanceof LineSegment) {
            return sprintf('lseg(%s,%s)',
                $this->pointType->serializeValue($val->getStart()),
                $this->pointType->serializeValue($val->getEnd())
            );
        }
        else {
            $this->throwInvalidValue($val);
        }
    }
}
