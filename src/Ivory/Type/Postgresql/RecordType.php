<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\ConnectionDependentObject;
use Ivory\Type\IConnectionDependentObject;

/**
 * An ad hoc record type.
 *
 * Represented as PHP arrays - lists of values.
 *
 * Ad hoc records are typically returned from `ROW()` expressions or from queries which construct the composition
 * on-the-fly, such as `WITH t (foo,foo2) AS (VALUES ('bar','baz')) SELECT t FROM t`, which returns a value
 * `('bar','baz')` of ad-hoc composite type `("foo"::text, "foo2"::text)`.
 *
 * Values are parsed from PostgreSQL external representation as strings (or `null` values) - although in PostgreSQL the
 * values might have been of other types, the type information is not passed to PHP. Also note that PostgreSQL does not
 * differentiate between `ROW()` and `ROW(NULL)` in their external text representation. As it cannot be decided in PHP
 * which was the original expression, `ROW()` is preferred.
 *
 * Serialization is automatic - the types are inferred from the values.
 */
class RecordType extends RowTypeBase implements IConnectionDependentObject
{
    use ConnectionDependentObject;

    protected function parseItem(int $pos, string $itemExtRepr)
    {
        return $itemExtRepr;
    }

    protected function makeParsedValue(array $items)
    {
        return $items;
    }

    protected function serializeBody(string &$result, $value): int
    {
        $typeDictionary = $this->getConnection()->getTypeDictionary();

        $cnt = 0;
        foreach ($value as $item) {
            if ($cnt > 0) {
                $result .= ',';
            }
            $type = $typeDictionary->requireTypeByValue($item);
            $result .= $type->serializeValue($item);
            $cnt++;
        }
        return $cnt;
    }
}
