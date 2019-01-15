<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;

/**
 * Binary data.
 *
 * Represented as the PHP `string` type.
 *
 * @see https://www.postgresql.org/docs/11/datatype-binary.html
 */
class BinaryType extends TypeBase implements ITotallyOrderedType
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

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } else {
            return $this->indicateType($strictType, "E'\\\\x" . bin2hex($val) . "'");
        }
    }
}
