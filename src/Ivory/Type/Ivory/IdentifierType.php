<?php
namespace Ivory\Type\Ivory;

use Ivory\Type\ITotallyOrderedType;

/**
 * Identifier, e.g., table or column name.
 *
 * Unlike {@link QuotedIdentifierType}, identifiers serialized by this converter are only quoted if containing
 * characters which would be illegal for an unquoted identifier according to the PostgreSQL lexical rules. To be
 * unquoted, the identifier must:
 * - start with a letter (`a`-`z` and other characters classified as letters) or an underscore (`_`), and
 * - subsequent characters (if any) can only be letters, underscores, digits (`0`-`9`), or dollar signs (`$`).
 * Identifiers not satisfying these constraints are quoted by this type converter, and thus will become case sensitive.
 *
 * As identifiers are expected to be used in contexts where `NULL` is illegal, serializing `null` will result in an
 * {@link \InvalidArgumentException}.
 *
 * Represented as the PHP `string` type.
 *
 * @see QuotedIdentifierType type converter always quoting the identifier
 * @see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
 */
class IdentifierType implements ITotallyOrderedType
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

        if ($this->needsQuotes($val)) {
            return '"' . str_replace('"', '""', $val) . '"';
        } else {
            return $val;
        }
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }

        return strcmp((string)$a, (string)$b); // FIXME: use the same comparison as StringType
    }

    private function needsQuotes(string $val): bool
    {
        return !preg_match(
            '~^
              [[:alpha:]_]
              [[:alnum:]_$]*
              $
             ~ux',
            $val
        );
    }
}
