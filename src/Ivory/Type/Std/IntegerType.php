<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;

/**
 * Signed integer.
 *
 * Represented as the PHP `int` type.
 *
 * @see https://www.postgresql.org/docs/11/datatype-numeric.html#DATATYPE-INT
 */
class IntegerType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr): int
    {
        return (int)$extRepr;
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } else {
            return $this->typeCastExpr($strictType, (string)(int)$val);
        }
    }
}
