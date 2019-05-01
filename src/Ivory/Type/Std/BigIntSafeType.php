<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\IType;

/**
 * Signed eight-byte integer.
 *
 * The PHP `int` representation is preferred. If, however, the value overflows `int` size, a string is returned
 * containing the decimal number.
 *
 * @see https://www.postgresql.org/docs/11/datatype-numeric.html#DATATYPE-INT
 */
class BigIntSafeType extends TypeBase implements ITotallyOrderedType
{
    public static function isIntegerString($str): bool
    {
        return (bool)preg_match('~^\s*-?[0-9]+\s*$~', $str);
    }

    public static function createForRange($min, $max, string $schemaName, string $typeName): IType
    {
        if (bccomp($min, (string)PHP_INT_MIN) >= 0 && bccomp($max, (string)PHP_INT_MAX) <= 0) {
            return new IntegerType($schemaName, $typeName);
        } else {
            return new BigIntSafeType($schemaName, $typeName);
        }
    }

    public function parseValue(string $extRepr)
    {
        if ($extRepr >= PHP_INT_MIN && $extRepr <= PHP_INT_MAX) {
            // correctness: int does not overflow, but rather gets converted to a float
            return (int)$extRepr;
        } else {
            return $extRepr;
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val >= PHP_INT_MIN && $val <= PHP_INT_MAX) {
            return $this->typeCastExpr($strictType, (string)(int)$val);
        } else {
            if (self::isIntegerString($val)) {
                return $this->typeCastExpr($strictType, (string)$val);
            } else {
                throw $this->invalidValueException($val);
            }
        }
    }
}
