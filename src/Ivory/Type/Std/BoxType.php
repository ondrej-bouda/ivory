<?php
namespace Ivory\Type\Std;

use Ivory\Value\Box;

/**
 * Rectangular box on a plane.
 *
 * Represented as a {@link \Ivory\Value\Box} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 */
class BoxType extends CompoundGeometricType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $re = '~^ \s*
                (\()? \s*                           # optional opening parenthesis
                ((?(1)\()[^,]+,[^,]+(?(1)\))) \s*   # corner; parenthesized if the whole segment is parenthesized
                , \s*
                ((?(1)\().+(?(1)\))) \s*            # opposite corner; parenthesized if the whole segment is parenthesized
                (?(1)\)) \s*                        # pairing closing parenthesis
                $~x';

        if (preg_match($re, $str, $m)) {
            try {
                $corner = $this->pointType->parseValue($m[2]);
                $oppositeCorner = $this->pointType->parseValue($m[3]);
                return Box::fromOppositeCorners($corner, $oppositeCorner);
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
        elseif ($val instanceof Box) {
            return sprintf('box(%s,%s)',
                $this->pointType->serializeValue($val->getUpperRight()),
                $this->pointType->serializeValue($val->getLowerLeft())
            );
        }
        else {
            $this->throwInvalidValue($val);
        }
    }
}
