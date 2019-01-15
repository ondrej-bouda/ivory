<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;

/**
 * Logical boolean (true/false).
 *
 * Represented as the PHP `bool` type.
 *
 * @see https://www.postgresql.org/docs/11/datatype-boolean.html
 */
class BooleanType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        switch (strtoupper($extRepr)) {
            case 'T':
            case 'TRUE':
            case 'Y':
            case 'YES':
            case 'ON':
            case '1':
                return true;

            case 'F':
            case 'FALSE':
            case 'N':
            case 'NO':
            case 'OFF':
            case '0':
                return false;

            default:
                throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val) {
            return 'TRUE';
        } else {
            return 'FALSE';
        }
    }
}
