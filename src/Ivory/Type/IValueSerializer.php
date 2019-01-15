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
     * In case `null` is given, the `'NULL'` string is returned (possibly typecast to the serialized type -- see below).
     *
     * The <tt>$strictType</tt> switch specifies the mode of serialization:
     * - strict type mode (<tt>true</tt>) means the result SQL expression will contain an explicit typecast or other
     *   suitable way so that PostgreSQL takes the value as of exactly the serialized type;
     * - loose type mode (<tt>false</tt>) results in an expression which may be used in the context a value of the
     *   serialized type is expected, i.e., will implicitly be converted by the standard PostgreSQL distribution to the
     *   requested type.
     *
     * E.g., in the strict mode, a `text` type serializer will return the string expression explicitly cast to the
     * `pg_catalog.text` type, wherease the loose mode would only output the string literal: `'abc'::text` (possibly
     * qualified with the `pg_catalog` schema if necessary) vs. mere `'abc'`. Similarly, a date serializer will return
     * `DATE '1998-01-23'` in the strict mode while just `'1998-01-23'` in the loose mode.
     *
     * The loose mode might improve readability of the generated SQL and may often be sufficient in usual situations:
     * inserting values to a table row, providing arguments to a PostgreSQL function, comparing values with other values
     * of a known type, etc. However, problems will arise in certain cases, e.g.:
     * - providing arguments to an overloaded PostgreSQL function -- PostgreSQL needs to decide which function to use,
     *   which it cannot from the external representation;
     * - `VALUES` expression used in a {@link https://www.postgresql.org/docs/current/queries-with.html `WITH`}
     *   expressions -- provided with just the external representation of the value, PostgreSQL would fallback to type
     *   `text`, which could not be typecast automatically to the target type;
     * - comparing two syntactically different while semantically equivalent values serialized by the same serializer --
     *   PostgreSQL needs to know the type so that the right comparison may be used.
     *
     * To stay on the safe side, the strict type is the default.
     *
     * @param mixed $val value to be serialized
     * @param bool $strictType <tt>true</tt> for the strict mode, <tt>false</tt> for the loose mode
     * @return string
     */
    function serializeValue($val, bool $strictType = true): string;
}
