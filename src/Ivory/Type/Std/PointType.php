<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Point;

/**
 * Point on a plane.
 *
 * Represented as a {@link \Ivory\Value\Point} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-geometric.html
 */
class PointType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        $coordsRe = '~^ \s* (\()? \s* ([0-9.e+-]+) \s* , \s* ([0-9.e+-]+) \s* (?(1)\)) \s* $~ix';
        if (preg_match($coordsRe, $extRepr, $m) && is_numeric($m[2]) && is_numeric($m[3])) {
            return Point::fromCoords($m[2], $m[3]);
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (is_array($val)) {
            $val = Point::fromCoords($val);
        } elseif (!$val instanceof Point) {
            throw $this->invalidValueException($val);
        }

        return sprintf('point(%s,%s)', $val->getX(), $val->getY());
    }
}
