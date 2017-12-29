<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Type\IValueSerializer;

/**
 * Serializer for quoted identifiers.
 *
 * By definition, quoted identifiers are always case sensitive.
 *
 * As quoted identifiers are expected to be used in contexts where `NULL` is illegal, serializing `null` will result in
 * an {@link \InvalidArgumentException}.
 *
 * @see IdentifierSerializer - a serializer only quoting the identifier if necessary due to character constraints
 * @see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
 */
class QuotedIdentifierSerializer implements IValueSerializer
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            throw new \InvalidArgumentException('Expecting an identifier, NULL encountered.');
        }

        return '"' . str_replace('"', '""', $val) . '"';
    }
}
