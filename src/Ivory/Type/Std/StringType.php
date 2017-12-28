<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Character string.
 *
 * Represented as the PHP `string` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-character.html
 */
class StringType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $str)
    {
        return $str;
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } else {
            return "'" . strtr((string)$val, ["'" => "''"]) . "'";
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        // FIXME: compare according to a collation, or at least the client encoding, or at least using UTF-8
        return strcmp((string)$a, (string)$b);
    }
}
