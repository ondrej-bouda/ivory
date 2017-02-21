<?php
namespace Ivory\Lang\Sql;

/**
 * @see https://www.postgresql.org/docs/9.6/static/datatype-numeric.html
 */
class Types
{
    const BIGINT_MIN = '-9223372036854775808'; // NOTE: written as string not to lose precision on platforms where this
    const BIGINT_MAX = '9223372036854775807';  //       would be converted to float

    /**
     * Lists names of types defined by SQL as reserved ones.
     *
     * The listed types are those the name of which is always treated as the corresponding PostgreSQL implementation
     * type. That is, whenever a user type was defined with the same name, the user type name would have to be written
     * with quotes. Without quotes, it is interpreted as the SQL type.
     *
     * See also https://www.postgresql.org/docs/9.6/static/datatype.html - the "Compatibility" box. That list, however,
     * only seems as an approximation. To get the real list of reserved type names in the sense described above, try to:
     * - `CREATE DOMAIN public.<type> AS TEXT` for any type in question;
     * - `SET search_path TO public, pg_catalog`;
     * - `SELECT pg_catalog.format_type('public.<type>'::regtype, NULL)`.
     * `<type>` is a reserved type name if and only if the result of the `SELECT` is enclosed in double quotes.
     *
     * Note that PostgreSQL function `format_type()` is not sufficient. E.g., it never outputs "int".
     *
     * @return string[][] map: lower-cased name of type defined by SQL => pair of schema and type name of the
     *                      implementing PostgreSQL type
     */
    public static function getReservedTypes()
    {
        return [
            'bigint' => ['pg_catalog', 'int8'],
            'bit' => ['pg_catalog', 'bit'],
            'bit varying' => ['pg_catalog', 'varbit'],
            // NOTE: bool is not reserved; i.e., a user-defined "bool" type might replace pg_catalog.bool
            'boolean' => ['pg_catalog', 'bool'],
            'char' => ['pg_catalog', 'bpchar'],
            'character' => ['pg_catalog', 'bpchar'],
            'character varying' => ['pg_catalog', 'varchar'],
            // NOTE: date is not reserved, despite being mentioned by the documentation. Try
            //       "CREATE DOMAIN public.date AS text; SET search_path TO public, pg_catalog; SELECT 1::date".
            'decimal' => ['pg_catalog', 'numeric'], // equivalent to numeric: see https://www.postgresql.org/docs/9.6/static/datatype-numeric.html
            'double precision' => ['pg_catalog', 'float8'],
            'int' => ['pg_catalog', 'int4'],
            'integer' => ['pg_catalog', 'int4'],
            'interval' => ['pg_catalog', 'interval'],
            'numeric' => ['pg_catalog', 'numeric'],
            'real' => ['pg_catalog', 'float4'],
            'smallint' => ['pg_catalog', 'int2'],
            'time' => ['pg_catalog', 'time'],
            'time with timezone' => ['pg_catalog', 'timetz'],
            'time without timezone' => ['pg_catalog', 'time'],
            'timestamp' => ['pg_catalog', 'timestamp'],
            'timestamp with timezone' => ['pg_catalog', 'timestamptz'],
            'timestamp without timezone' => ['pg_catalog', 'timestamp'],
            'varchar' => ['pg_catalog', 'varchar'],
            // NOTE: xml is not reserved, despite being mentioned by the documentation.
        ];
    }
}
