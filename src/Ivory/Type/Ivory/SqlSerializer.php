<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Type\IValueSerializer;

/**
 * Internal Ivory serializer passing the given value un-encoded to output as the SQL value.
 *
 * Used in SQL patterns to handle `%sql` placeholders.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class SqlSerializer implements IValueSerializer
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            throw new \InvalidArgumentException('Expecting an SQL string, NULL encountered.');
        }

        return $val;
    }
}
