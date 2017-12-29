<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Circle;

/**
 * Circle on a plane.
 *
 * Represented as a {@link \Ivory\Value\Circle} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class CircleType extends CompoundGeometricType
{
    public function parseValue(string $str)
    {
        $re = '~^ \s*
                ((\()|(<))? \s*                     # optional opening parenthesis or angle bracket
                ((?(3)\()[^,]+,[^,]+(?(3)\))) \s*   # center point; parenthesized if the whole circle is parenthesized
                , \s*
                ([0-9.e+-]+) \s*                    # radius
                (?(1)\)|(?(2)>)) \s*                # pairing closing parenthesis or angle bracket
                $~ix';

        if (preg_match($re, $str, $m) && is_numeric($m[5])) {
            try {
                $center = $this->pointType->parseValue($m[4]);
                return Circle::fromCoords($center, $m[5]);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($str, $e);
            }
        } else {
            throw $this->invalidValueException($str);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
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
