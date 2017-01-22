<?php
namespace Ivory\Type\Ivory;

/**
 * Internal Ivory converter passing the given value unencoded to output as the SQL value.
 *
 * Used in SQL patterns to handle `%sql` placeholders.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class SqlType extends PatternTypeBase
{
    public function serializeValue($val)
    {
        if ($val === null) {
            throw new \InvalidArgumentException('Expecting an SQL string, NULL encountered.');
        }

        return $val;
    }
}
