<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;

/**
 * An immutable representation of an SQL pattern.
 *
 * An SQL pattern is a plain SQL string with special placeholders for parameters.
 *
 * There are two kinds of parameters which might be used in a pattern:
 * - *named parameters* - these are specified by an explicit name; and
 * - *positional parameters* - specified solely by their position relative to other positional parameters.
 *
 * Multiple occurrences of the same named parameter may be used in the SQL pattern, referring to the same one value. On
 * the other hand, positional parameters may not be reused - a value must be provided for each positional parameter, and
 * placeholders may not explicitly refer to positional parameter values.
 *
 * Placeholders use the following syntax in SQL patterns:
 * <pre>
 * %[type][:name]
 * </pre>
 * where:
 * * `name` is the name of the parameter (if not specified, the parameter is treated as a positional parameter); and
 * * `type` is an explicit type specification, governing how the value given for the parameter will be encoded to the
 *   SQL string. If type is not given, it is inferred from the actual data type of the parameter value.
 *
 * Examples:
 * * valid `name`s: `tbl`, `person_id`, `p1`;
 * * valid `type` specifications: `s`, `int_singleton`, `t1`, `public.planet`, `public.planet[]`, `int[][]`,
 *   `public."int"`, `"my schema"."my type"`, `{double precision}`.
 *
 * Detailed rules on syntax and the corresponding semantics follow:
 * * Let us define a "token", first, which in this context means a string of one or more letters, digits and underscore
 *   characters (`_`), not starting with a digit.
 * * `name` may only be a single token. Especially, the name may not be a number - referring to positional arguments is
 *   not supported.
 * * `type` may either be:
 *   * a single token, or
 *   * any string enclosed in double quotes (the `"` character; to write a double quote character literally, use two of
 *     them), or
 *   * any string within a pair of curly braces (the `{` and `}`); note there is no way to type the closing brace
 *     literally using this variant).
 * * The `type` may optionally be prefixed with `typeschema.`, where `typeschema` meets the same syntax rules as `type`.
 * * The `type` specification may be ended with an empty pair of square brackets, indicating an array type. (Multiple
 *   pairs of brackets are accepted, although these are treated the same as a mere one bracket pair, consistently with
 *   what PostgreSQL does.) Note, however, that only empty pairs of brackets are recognized as part of the type
 *   specification. An array placeholder immediately followed by a subscript works as expected: `SELECT %bigint[][2]`
 *   selects the item under index 2 from the provided array.
 * * Names of available types, as well as the rules inferring the type automatically (when the type is not specified),
 *   are defined by the {@link Ivory\Type\TypeDictionary} used by the connection for which the recipe will be serialized
 *   to an SQL string. The standard Ivory deployment registers all types defined in the connected-to database under
 *   their fully qualified names (e.g., `public.sometype`) and also some aliases, especially those corresponding to the
 *   SQL reserved types (e.g., `int`). Besides, some custom types special for being used in SQL patterns may be
 *   registered (e.g., `sql`).
 * * Registered types need not be schema-qualified. Just the name of the type is sufficient - the PostgreSQL
 *   `search_path` facility is leveraged to identify the type. Before the `search_path` schemas are actually searched,
 *   custom types and type aliases are considered.
 * * Note the difference between quoted type name and an unquoted one (i.e., using the first or the third syntax) is the
 *   same as for PostgreSQL: a quoted type name is case sensitive and it cannot refer to an
 *   {@link \Ivory\Lang\Sql\Types::getReservedTypes() SQL reserved type}. Recall specifying, e.g., `SELECT 1::"int"`
 *   addresses a user-defined type named `int`, while `SELECT 1::int` always refers to the reserved type, regardless of
 *   `search_path` or any user-defined types. This is, actually, also the reason for the curly braces syntax -
 *   otherwise, multi-word SQL reserved types (such as `double precision`) could not be specified. The braces syntax
 *   may, of course, be used for regular built-in or user-defined types as long as there is no conflicting reserved type
 *   (which would be registered with the `TypeDictionary` as an alias).
 *
 * In specific situations, multiple same-named placeholders may be used with different type specifications, e.g.,
 * `SELECT id FROM %ident:tbl UNION SELECT object_id FROM log WHERE table = %s:tbl`. This is perfectly legal - a single
 * value for the `tbl` parameter will be encoded as an identifier for the first placeholder and as a string literal for
 * the second placeholder.
 *
 * To use a literal `%` in the SQL string, type `%%`.
 *
 * The percent signs are searched in the whole string, regardless of the surrounding content. Namely, a percent sign
 * inside string constants written in the SQL string literally *will* be interpreted as a placeholder and replaced with
 * a provided value.
 *
 * Note that even {@link IRelationRecipe} and {@link ICommandRecipe} objects may be used as parameter values to form a
 * more complex recipe, e.g., a
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html Common Table Expression}.
 *
 * @internal Ivory design note: The positional parameters could have been treated as parameters named by their
 * zero-based position. This is not the case, though. If placeholders could refer to positional parameters (e.g.,
 * <tt>%s:0</tt>), it would only complicate the specification without any significant benefit. Especially the
 * {@link SqlRecipe::fromFragments()} would be overcomplicated as the placeholders would have to be re-numbered.
 *
 * @internal Ivory design note: The common placeholder syntax ":name" is intentionally unsupported. PostgreSQL uses
 * <tt>::type</tt> for typecasts, which could be mistaken for the named arguments written as ":name". Moreover, it would
 * collide with Embedded SQL which also uses the same syntax. Rather than complicating the queries with escaping, Ivory
 * requires the leading % sign, which also simplifies parsing the patterns - both for the machine and for the humans.
 */
class SqlPattern
{
    private $sqlTorso;
    /** @var SqlPatternPlaceholder[] */
    private $positionalPlaceholders;
    /** @var SqlPatternPlaceholder[][] */
    private $namedPlaceholderMap;
    /** @var SqlPatternPlaceholder[] */
    private $placeholderSequence;

    /**
     * @param string $sqlTorso torso of the SQL parsed from the described SQL pattern; this is the pattern with removed
     *                           placeholders and unescaped <tt>%%</tt> sequences
     * @param SqlPatternPlaceholder[] $positionalPlaceholders
     *                                  list of positional placeholders, in order of appearance, used in the described
     *                                    SQL pattern
     * @param SqlPatternPlaceholder[][] $namedPlaceholderMap
     *                                  map of named placeholders used in the described SQL pattern: name => list of all
     *                                    placeholders (in order of appearance) referring to the parameter name
     */
    public function __construct(string $sqlTorso, array $positionalPlaceholders, array $namedPlaceholderMap)
    {
        assert(
            !$positionalPlaceholders || array_keys($positionalPlaceholders) == range(0, count($positionalPlaceholders) - 1),
            new \InvalidArgumentException('$positionalPlaceholders array is not a list - keys do not form a sequence')
        );

        $this->sqlTorso = $sqlTorso;
        $this->positionalPlaceholders = $positionalPlaceholders;
        $this->namedPlaceholderMap = $namedPlaceholderMap;

        $this->initPlaceholderSequence();
    }

    private function initPlaceholderSequence()
    {
        $byOffset = [];
        foreach ($this->positionalPlaceholders as $plcHld) {
            $byOffset[$plcHld->getOffset()] = $plcHld;
        }
        foreach ($this->namedPlaceholderMap as $plcHlds) {
            foreach ($plcHlds as $plcHld) {
                $byOffset[$plcHld->getOffset()] = $plcHld;
            }
        }
        ksort($byOffset);
        $this->placeholderSequence = array_values($byOffset);
    }

    /**
     * Returns the described SQL pattern with placeholders removed and sequences `%%` unescaped.
     *
     * Parameter values must be inserted in this string in place of the removed placeholders to form a valid SQL
     * statement. Use {@link fillSql()} or {@link generateSql()} for that.
     */
    public function getSqlTorso() : string
    {
        return $this->sqlTorso;
    }

    /**
     * @return SqlPatternPlaceholder[] list of positional placeholders, in order of appearance, used in the
     *                                       described SQL pattern
     */
    public function getPositionalPlaceholders() : array
    {
        return $this->positionalPlaceholders;
    }

    /**
     * @return SqlPatternPlaceholder[][] map of named placeholders used in the described SQL pattern: name => list
     *                                         of all placeholders (in order of appearance) referring to the parameter
     *                                         name
     */
    public function getNamedPlaceholderMap() : array
    {
        return $this->namedPlaceholderMap;
    }

    /**
     * Fills gaps in the SQL torso with given SQL strings to form a complete SQL string.
     *
     * This is merely a utility method concatenating the right parts of strings. The given parameter values must already
     * be encoded, escaped, whatsoever, so that they may just be inserted in the gap. Each of the values is explicitly
     * cast to `string`.
     *
     * Assertions are used for checking the parameters as the real sanity check should be performed in higher levels -
     * here the argument should be correct. `InvalidArgumentException` is thrown by the assertion if the parameter value
     * map contain insufficient or extra parameters.
     *
     * @param string[] $parameterValueSqlStrings map: parameter position or name => SQL string encoding the parameter
     *                                             value
     * @return string SQL string
     */
    public function fillSql(array $parameterValueSqlStrings) : string
    {
        assert(
            !array_diff_key(
                $this->positionalPlaceholders + $this->namedPlaceholderMap,
                $parameterValueSqlStrings
            ),
            new \InvalidArgumentException('Insufficient parameter values specified to be filled in the pattern.')
        );

        assert(
            !array_diff_key(
                $parameterValueSqlStrings,
                $this->positionalPlaceholders + $this->namedPlaceholderMap
            ),
            new \InvalidArgumentException('Extra parameter values specified to be filled in the pattern.')
        );

        $result = '';
        $offset = 0;
        foreach ($this->placeholderSequence as $plcHld) {
            $val = $parameterValueSqlStrings[$plcHld->getNameOrPosition()];
            $result .= substr($this->sqlTorso, $offset, $plcHld->getOffset() - $offset) . $val;
            $offset = $plcHld->getOffset();
        }
        $result .= substr($this->sqlTorso, $offset);

        return $result;
    }

    /**
     * Generate SQL string from this pattern with encoded parameter values requested to be filled by the caller.
     *
     * This is a more general method to {@link fillSql()}. The reason for this method is that a single named parameter
     * may have multiple placeholders within the pattern, each with a different type specification. Therefore, each
     * occurrence might result in different encoding. This method presents the caller each placeholder, one by one, so
     * that the caller provides the encoded parameter value.
     *
     * Technically, this is achieved by returning a `\Generator` being treated as a coroutine. Each placeholder is
     * yielded from the generator as a {@link SqlPatternPlaceholder} object, describing the placeholder to fill the
     * value for. The caller has to {@link \Generator::send() send()} the encoded value for this placeholder, and then
     * take the next placeholder. After iterating over all placeholders for which a value was requested, the final SQL
     * string may be retrieved by calling {@link \Generator::getReturn()} on the generator.
     *
     * The encoded values sent to the generator are treated as strings. They has to either be strings or other types
     * convertible to strings. `null`, however, is considered as if the caller forgot to provide a value at all, and a
     * {@link NoDataException} is thrown in such a case.
     *
     * Example:
     * <code>
     * <?php
     * $pattern = new SqlPattern(
     *     'SELECT id FROM  UNION SELECT object_id FROM log WHERE table = ',
     *     [new SqlPatternPlaceholder(15, 'tbl', 'ident'), new SqlPatternPlaceholder(62, 'tbl', 'string')],
     *     []
     * );
     * $gen = $pattern->generateSql();
     * while ($gen->valid()) {
     *     $placeholder = $gen->current();
     *     $serializedValue = ($placeholder->getTypeName() == 'string' ? "'person'" : 'person');
     *     $gen->send($serializedValue);
     * }
     * echo $gen->getReturn(); // prints "SELECT id FROM person UNION SELECT object_id FROM log WHERE table = 'person'"
     * </code>
     *
     * @throws NoDataException if no encoded value is sent for a yielded parameter
     */
    public function generateSql() : \Generator
    {
        $result = '';
        $offset = 0;
        foreach ($this->placeholderSequence as $plcHdr) {
            $encodedValue = yield $plcHdr;
            assert(
                $encodedValue !== null,
                new NoDataException(
                    "No value encoded for placeholder {$plcHdr->getNameOrPosition()} at offset {$plcHdr->getOffset()}."
                )
            );
            $result .= substr($this->sqlTorso, $offset, $plcHdr->getOffset() - $offset) . $encodedValue;
            $offset = $plcHdr->getOffset();
        }
        $result .= substr($this->sqlTorso, $offset);

        return $result;
    }
}
