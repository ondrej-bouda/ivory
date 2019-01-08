<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * Serializer of PHP values to the corresponding PostgreSQL values.
 */
interface IValueSerializer
{
    /**
     * Serializes a value of the represented type to a string to be pasted in the SQL query.
     *
     * In case `null` is given, the `'NULL'` string is returned.
     *
     * @param mixed $val value to be serialized
     * @param bool $forceType whether the SQL expression should tell PostgreSQL the type explicitly, if relevant
     * @return string
     */
    function serializeValue($val, bool $forceType = false): string;
}
