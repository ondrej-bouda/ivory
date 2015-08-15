<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\Point;

/**
 * Point on a plane.
 *
 * Represented as a {@link \Ivory\Value\Point} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 */
class PointType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        elseif (preg_match('~^ \s* (\()? \s* ([0-9.e+-]+) \s* , \s* ([0-9.e+-]+) \s* (?(1)\)) \s* $~ix', $str, $m) &&
                is_numeric($m[2]) && is_numeric($m[3]))
        {
            return Point::fromCoords($m[2], $m[3]);
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
        elseif ($val instanceof Point) {
            return sprintf('point(%s,%s)', $val->getX(), $val->getY());
        }
        else {
            $this->throwInvalidValue($val);
        }
    }
}
