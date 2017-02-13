<?php
namespace Ivory\Type\Std;

use Ivory\Value\Line;
use Ivory\Value\LineSegment;
use Ivory\Value\Polygon;

/**
 * Polygon on a plane.
 *
 * Represented as a {@link \Ivory\Value\Polygon} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class PolygonType extends CompoundGeometricType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $re = '~^ \s*
                (\()? \s*                           # optional opening parenthesis
                (
                  (\()?[^,]+,[^,]+(?(3)\)) \s*      # the first endpoint; optionally parenthesized
                  (?:                               # the rest of the endpoints
                    , \s*
                    (?(3)\()[^,]+,[^,]+(?(3)\)) \s* # parenthesized iff the first endpoint is parenthesized
                  )*
                )
                (?(1)\)) \s*                        # pairing closing parenthesis
                $~x';

        if (preg_match($re, $str, $m)) {
            $points = [];
            $pointListStr = $m[2];
            $fragments = explode(',', $pointListStr);
            try {
                for ($i = 0; $i < count($fragments); $i += 2) {
                    $points[] = $this->pointType->parseValue($fragments[$i] . ',' . $fragments[$i + 1]);
                }
                return Polygon::fromPoints($points);
            } catch (\InvalidArgumentException $e) {
                $this->throwInvalidValue($str, $e);
            }
        } else {
            $this->throwInvalidValue($str);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Polygon) {
            try {
                $val = Polygon::fromPoints($val);
            } catch (\InvalidArgumentException $e) {
                $this->throwInvalidValue($val, $e);
            }
        }

        $points = [];
        foreach ($val->getPoints() as $point) {
            $points[] = $this->pointType->serializeValue($point);
        }

        return sprintf("polygon '(%s)'", implode(',', $points));
    }
}
