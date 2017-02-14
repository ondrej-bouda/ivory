<?php
namespace Ivory\Type\Ivory;

use Ivory\Type\ITotallyOrderedType;

/**
 * Quoted identifier.
 *
 * By definition, quoted identifiers are always case sensitive.
 *
 * As quoted identifiers are expected to be used in contexts where `NULL` is illegal, serializing `null` will result in
 * an {@link \InvalidArgumentException}.
 *
 * Represented as the PHP `string` type.
 *
 * @see IdentifierType type converter only quoting the identifier if necessary due to character constraints
 * @see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
 */
class QuotedIdentifierType implements ITotallyOrderedType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return $str;
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            throw new \InvalidArgumentException('Expecting an identifier, NULL encountered.');
        }

        return '"' . str_replace('"', '""', $val) . '"';
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }
        return strcmp((string)$a, (string)$b); // FIXME: use the same comparison as StringType
    }
}
