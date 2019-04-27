<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\TupleId;

/**
 * PostgreSQL tuple identifier type.
 *
 * Represented as a {@link \Ivory\Value\TupleId} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
class TupleIdType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        if (!preg_match('~^ \( (\d+) , (\d+) \) $~x', $extRepr, $m)) {
            throw new \InvalidArgumentException('Invalid tuple ID: ' . $extRepr);
        }

        return TupleId::fromCoordinates((int)$m[1], (int)$m[2]);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if ($val instanceof TupleId) {
            $sqlExpr = "'({$val->getBlockNumber()},{$val->getTupleIndex()})'";
            return $this->indicateType($strictType, $sqlExpr);
        } else {
            throw new \InvalidArgumentException('Unsupported type of value to serialize to a tuple ID');
        }
    }
}
