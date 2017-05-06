<?php
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
     * @todo unify whether the result shall contain the typecast or not; whether it is necessary depends on the context, so the output of this method shall probably contain no typecasts - the caller should include it, if required
     * @param mixed $val
     * @return string
     */
    function serializeValue($val): string;
}
