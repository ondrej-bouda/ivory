<?php
namespace Ivory\Type\Std;

use Ivory\Type\ITotallyOrderedType;

/**
 * Character string.
 *
 * Represented as the PHP `string` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-character.html
 */
class StringType extends \Ivory\Type\BaseType implements ITotallyOrderedType
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
            return "'" . strtr($val, ["'" => "''"]) . "'";
        }
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }
        // FIXME: compare according to a collation, or at least the client encoding, or at least using UTF-8
        return strcmp((string)$a, (string)$b);
    }
}
