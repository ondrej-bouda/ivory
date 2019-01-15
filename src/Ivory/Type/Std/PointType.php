<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Point;

/**
 * Point on a plane.
 *
 * Represented as a {@link \Ivory\Value\Point} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-geometric.html
 */
class PointType extends TypeBase implements ITotallyOrderedType
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

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (is_array($val)) {
            $val = Point::fromCoords($val);
        } elseif (!$val instanceof Point) {
            throw $this->invalidValueException($val);
        }

        return sprintf('point(%s,%s)', $val->getX(), $val->getY());
    }
}
