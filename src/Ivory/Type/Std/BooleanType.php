<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Type\ITotallyOrderedType;

/**
 * Logical boolean (true/false).
 *
 * Represented as the PHP `bool` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-boolean.html
 */
class BooleanType extends \Ivory\Type\BaseType implements ITotallyOrderedType
{
    public function parseValue(string $str)
    {
        switch (strtoupper($str)) {
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
                $this->throwInvalidValue($str);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val) {
            return 'TRUE';
        } else {
            return 'FALSE';
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        return (bool)$a - (bool)$b;
    }
}
