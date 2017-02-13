<?php
namespace Ivory\Type\Std;

use Ivory\Value\Line;
use Ivory\Value\LineSegment;
use Ivory\Value\Path;
use Ivory\Value\Polygon;

/**
 * Path on a plane.
 *
 * Represented as a {@link \Ivory\Value\Path} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class PathType extends CompoundGeometricType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $re = '~^ \s*
                (?:(\()|(\[))? \s*                  # optional opening parenthesis or bracket
                (
                  (\()?[^,]+,[^,]+(?(4)\)) \s*      # the first endpoint; optionally parenthesized
                  (?:                               # the rest of the endpoints
                    , \s*
                    (?(4)\()[^,]+,[^,]+(?(4)\)) \s* # parenthesized iff the first endpoint is parenthesized
                  )*
                )
                (?(1)\)|(?(2)\])) \s*               # pairing closing parenthesis or bracket
                $~x';

        if (preg_match($re, $str, $m)) {
            $isOpen = isset($m[2]);
            $points = [];
            $pointListStr = $m[3];
            $fragments = explode(',', $pointListStr);
            try {
                for ($i = 0; $i < count($fragments); $i += 2) {
                    $points[] = $this->pointType->parseValue($fragments[$i] . ',' . $fragments[$i + 1]);
                }
                return Path::fromPoints($points, ($isOpen ? Path::OPEN : Path::CLOSED));
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

        if (!$val instanceof Path) {
            try {
                $val = Path::fromPoints($val);
            } catch (\InvalidArgumentException $e) {
                $this->throwInvalidValue($val, $e);
            }
        }

        $points = [];
        foreach ($val->getPoints() as $point) {
            $points[] = $this->pointType->serializeValue($point);
        }

        return sprintf("path '%s%s%s'",
            ($val->isOpen ? '[' : '('),
            implode(',', $points),
            ($val->isOpen ? ']' : ')')
        );
    }
}
