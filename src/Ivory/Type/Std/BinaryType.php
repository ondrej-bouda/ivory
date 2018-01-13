<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Binary data.
 *
 * Represented as the PHP `string` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-binary.html
 */
class BinaryType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        /* Depending on the PostgreSQL bytea_output configuration parameter, data may be encoded either in the "hex" or
         * "escape" format, which may be recognized by the '\x' prefix.
         */
        if (substr($extRepr, 0, 2) == '\\x') {
            return hex2bin(substr($extRepr, 2));
        } else {
            return pg_unescape_bytea($extRepr);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } else {
            return "E'\\\\x" . bin2hex($val) . "'";
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        return strcmp((string)$a, (string)$b);
    }
}
