<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Exception\IncomparableException;
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
    public function parseValue(string $str)
    {
        $coordsRe = '~^ \s* (\()? \s* ([0-9.e+-]+) \s* , \s* ([0-9.e+-]+) \s* (?(1)\)) \s* $~ix';
        if (preg_match($coordsRe, $str, $m) && is_numeric($m[2]) && is_numeric($m[3])) {
            return Point::fromCoords($m[2], $m[3]);
        } else {
            throw $this->invalidValueException($str);
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

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }

        if (is_array($a)) {
            $a = Point::fromCoords($a);
        } elseif (!$a instanceof Point) {
            throw new IncomparableException('$a is not a ' . Point::class);
        }

        if (is_array($b)) {
            $b = Point::fromCoords($b);
        } elseif (!$b instanceof Point) {
            throw new IncomparableException('$b is not a ' . Point::class);
        }

        $ac = $a->toCoords();
        $bc = $b->toCoords();

        $xComp = FloatType::compareFloats($ac[0], $bc[0]);
        if ($xComp !== 0) {
            return $xComp;
        } else {
            return FloatType::compareFloats($ac[1], $bc[1]);
        }
    }
}
