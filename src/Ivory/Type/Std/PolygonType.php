<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Polygon;

/**
 * Polygon on a plane.
 *
 * Represented as a {@link \Ivory\Value\Polygon} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-geometric.html
 */
class PolygonType extends CompoundGeometricType
{
    public function parseValue(string $extRepr)
    {
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

        if (preg_match($re, $extRepr, $m)) {
            $points = [];
            $pointListStr = $m[2];
            $fragments = explode(',', $pointListStr);
            try {
                for ($i = 0; $i < count($fragments); $i += 2) {
                    $points[] = $this->pointType->parseValue($fragments[$i] . ',' . $fragments[$i + 1]);
                }
                return Polygon::fromPoints($points);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($extRepr, $e);
            }
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof Polygon) {
            try {
                $val = Polygon::fromPoints($val);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($val, $e);
            }
        }

        $points = [];
        foreach ($val->getPoints() as $point) {
            $points[] = $this->pointType->serializeValue($point, false);
        }

        return $this->indicateType($strictType, sprintf("'(%s)'", implode(',', $points)));
    }
}
