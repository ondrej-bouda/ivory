<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Type\IValueSerializer;

/**
 * Identifier, e.g., table or column name.
 *
 * Unlike {@link QuotedIdentifierSerializer}, identifiers serialized by this serializer are only quoted if reasonable.
 * There are two reasons for quoting:
 * - uppercase characters in the identifier (without quoting, Postgres would convert the identifier to lowercase), or
 * - characters which would be illegal for an unquoted identifier according to the PostgreSQL lexical rules.
 *
 * Thus, to be unquoted, the identifier must:
 * - start with a lowercase letter (`a`-`z` and other characters classified as letters) or an underscore (`_`), and
 * - subsequent characters (if any) can only be lowercase letters, underscores, digits (`0`-`9`), or dollar signs (`$`).
 * Identifiers not satisfying these constraints are quoted by this serializer.
 *
 * As identifiers are expected to be used in contexts where `NULL` is illegal, serializing `null` will result in an
 * {@link \InvalidArgumentException}.
 *
 * @see QuotedIdentifierSerializer - a serializer always quoting the identifier
 * @see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
 */
class IdentifierSerializer implements IValueSerializer
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            throw new \InvalidArgumentException('Expecting an identifier, NULL encountered.');
        }

        if ($this->needsQuotes($val)) {
            return '"' . str_replace('"', '""', $val) . '"';
        } else {
            return $val;
        }
    }

    private function needsQuotes(string $val): bool
    {
        return !preg_match(
            '~^
              [[:lower:]_]
              [[:lower:][:digit:]_$]*
              $
             ~ux',
            $val
        );
    }
}
