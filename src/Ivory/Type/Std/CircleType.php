<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Circle;

/**
 * Circle on a plane.
 *
 * Represented as a {@link \Ivory\Value\Circle} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-geometric.html
 */
class CircleType extends CompoundGeometricType
{
    public function parseValue(string $extRepr)
    {
        $re = '~^ \s*
                ((\()|(<))? \s*                     # optional opening parenthesis or angle bracket
                ((?(3)\()[^,]+,[^,]+(?(3)\))) \s*   # center point; parenthesized if the whole circle is parenthesized
                , \s*
                ([0-9.e+-]+) \s*                    # radius
                (?(1)\)|(?(2)>)) \s*                # pairing closing parenthesis or angle bracket
                $~ix';

        if (preg_match($re, $extRepr, $m) && is_numeric($m[5])) {
            try {
                $center = $this->pointType->parseValue($m[4]);
                return Circle::fromCoords($center, $m[5]);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($extRepr, $e);
            }
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        } elseif ($val instanceof Circle) {
            return sprintf('circle(%s,%s)',
                $this->pointType->serializeValue($val->getCenter()),
                $val->getRadius()
            );
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
