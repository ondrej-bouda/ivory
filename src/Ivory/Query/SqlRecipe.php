<?php
namespace Ivory\Query;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\NoDataException;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Lang\SqlPattern\SqlPatternPlaceholder;
use Ivory\Type\ITypeDictionary;
use Ivory\Utils\StringUtils;

/**
 * Recipe defined by an SQL query string or a parametrized SQL pattern.
 *
 * Note this class is not directly instantiable. Instead, use factory methods on subclasses.
 *
 * The SQL pattern is a plain SQL string with special placeholders. Parameter values, given either at the time of the
 * recipe creation or specified later on, are substituted for the placeholders.
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
abstract class SqlRecipe
{
    /** @var SqlPattern */
    private $sqlPattern;
    /** @var array map: parameter name or position => supplied value */
    private $params;
    /** @var bool[] map: name of parameter which has not been set any value yet => <tt>true</tt> value */
    private $unsatisfiedParams;


    /**
     * Creates an SQL recipe from an SQL string.
     *
     * No parameter substitution is performed on the string - it is used as is.
     *
     * @param string $sql SQL string
     * @return static
     */
    public static function fromSql(string $sql) : self
    {
        $sqlPattern = new SqlPattern($sql, [], []);
        return new static($sqlPattern, []);
    }

    /**
     * Creates a new recipe from an SQL pattern.
     *
     * Values for all positional parameters required by the pattern must be given.
     *
     * Example:
     * <pre>
     * <?php
     * $recipe = new SqlRecipe(
     *   'SELECT *, %s:status FROM person WHERE role = %d AND email = %s',
     *   4, 'john@doe.com'
     * );
     * // results in "SELECT * FROM person WHERE role = 4 AND email = 'john@doe.com'"
     * </pre>
     *
     * Performance considerations: parsing the SQL pattern, if given as a string, is done by the parser obtained by
     * {@link \Ivory\Ivory::getSqlPatternParser()}. Depending on Ivory configuration, the parser will cache the results
     * and reuse them for the same pattern next time.
     *
     * @param string|SqlPattern $sqlPattern
     * @param array ...$positionalParameters
     * @return static
     * @throws \InvalidArgumentException when the number of provided positional parameters differs from the number of
     *                                     positional parameters required by the pattern
     */
    public static function fromPattern($sqlPattern, ...$positionalParameters) : self
    {
        if (!$sqlPattern instanceof SqlPattern) {
            $parser = \Ivory\Ivory::getSqlPatternParser();
            $sqlPattern = $parser->parse($sqlPattern);
        }

        if (count($sqlPattern->getPositionalPlaceholders()) != count($positionalParameters)) {
            throw new \InvalidArgumentException(sprintf(
                'The SQL pattern requires %d positional parameters, %d given.',
                count($sqlPattern->getPositionalPlaceholders()),
                count($positionalParameters)
            ));
        }

        return new static($sqlPattern, $positionalParameters);
    }

    /**
     * Creates an SQL recipe from one or more fragments, each with its own positional parameters.
     *
     * Each fragment must be immediately followed by all positional arguments it requires. Then, another fragment may
     * follow.
     *
     * The fragments get concatenated to form the resulting SQL pattern. A single space is added between each two
     * fragments.
     *
     * Named parameters are shared among fragments. In other words, if two fragments use the same named parameter,
     * specifying the parameter by {@link setParam()} will substitute the same value to both fragments.
     *
     * Example:
     * <pre>
     * <?php
     * $recipe = SqlRecipe::fromFragments(
     *   'SELECT * FROM person WHERE role = %d', 4, 'AND email = %s', 'john@doe.com'
     * );
     * // results in "SELECT * FROM person WHERE role = 4 AND email = 'john@doe.com'"
     * </pre>
     *
     * Performance considerations: parsing the SQL pattern, if given as a string, is done by the parser obtained by
     * {@link \Ivory\Ivory::getSqlPatternParser()}. Depending on Ivory configuration, the parser will cache the results
     * and reuse them for the same pattern next time.
     *
     * @internal Ivory design note: The single space added between each two fragments aspires to be more practical than
     * a mere concatenation, which would require the user to specify spaces where the next fragment immediately
     * continued with the query.
     *
     * @param string|SqlPattern $fragment
     * @param array ...$fragmentsAndPositionalParams further fragments (each of which is either a <tt>string</tt> or an
     *                                                 {@link SqlPattern} object) and values of their parameters
     * @return static
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     */
    public static function fromFragments($fragment, ...$fragmentsAndPositionalParams) : self
    {
        $overallSqlTorso = '';
        $overallPosPlaceholders = [];
        $overallNamedPlaceholderMap = [];
        $overallPosParams = [];

        $curFragment = $fragment;
        $curFragmentNum = 1;
        $argsProcessed = 0;
        do {
            // process the fragment
            if (!$curFragment instanceof SqlPattern) {
                if (is_string($curFragment)) {
                    $parser = \Ivory\Ivory::getSqlPatternParser();
                    $curFragment = $parser->parse($curFragment);
                }
                else {
                    $ord = StringUtils::englishOrd($curFragmentNum);
                    throw new \InvalidArgumentException("Invalid type of $ord fragment. Isn't it a misplaced parameter value?");
                }
            }

            // add to the overall pattern
            if ($argsProcessed > 0 && !preg_match('~^\s~', $curFragment->getSqlTorso())) {
                $overallSqlTorso .= ' ';
            }
            $sqlTorsoOffset = strlen($overallSqlTorso);
            $overallSqlTorso .= $curFragment->getSqlTorso();
            foreach ($curFragment->getPositionalPlaceholders() as $plcHdr) {
                $overallPlcHdr = new SqlPatternPlaceholder(
                    $sqlTorsoOffset + $plcHdr->getOffset(),
                    count($overallPosPlaceholders),
                    $plcHdr->getTypeName(),
                    $plcHdr->isTypeNameQuoted(),
                    $plcHdr->getSchemaName(),
                    $plcHdr->isSchemaNameQuoted()
                );
                $overallPosPlaceholders[] = $overallPlcHdr;
            }
            foreach ($curFragment->getNamedPlaceholderMap() as $name => $occurrences) {
                /** @var SqlPatternPlaceholder[] $occurrences */
                if (!isset($overallNamedPlaceholderMap[$name])) {
                    $overallNamedPlaceholderMap[$name] = [];
                }
                foreach ($occurrences as $plcHdr) {
                    $overallPlcHdr = new SqlPatternPlaceholder(
                        $sqlTorsoOffset + $plcHdr->getOffset(),
                        $name,
                        $plcHdr->getTypeName(),
                        $plcHdr->isTypeNameQuoted(),
                        $plcHdr->getSchemaName(),
                        $plcHdr->isSchemaNameQuoted()
                    );
                    $overallNamedPlaceholderMap[$name][] = $overallPlcHdr;
                }
            }

            // values of parameters
            $plcHdrCnt = count($curFragment->getPositionalPlaceholders());
            $posParams = array_slice($fragmentsAndPositionalParams, $argsProcessed, $plcHdrCnt);
            if (count($posParams) == $plcHdrCnt) {
                $overallPosParams = array_merge($overallPosParams, $posParams);
            }
            else {
                $ord = StringUtils::englishOrd($curFragmentNum);
                throw new \InvalidArgumentException("Not enough positional parameters for the $ord fragment");
            }

            $curFragmentNum++;
            $argsProcessed += count($posParams);

            $curFragment =& $fragmentsAndPositionalParams[$argsProcessed];
            $argsProcessed++;
        } while (isset($curFragment));

        $overallPattern = new SqlPattern($overallSqlTorso, $overallPosPlaceholders, $overallNamedPlaceholderMap);

        return new static($overallPattern, $overallPosParams);
    }

    final private function __construct(SqlPattern $sqlPattern, array $positionalParameters)
    {
        $this->sqlPattern = $sqlPattern;
        $this->params = $positionalParameters;
        $this->unsatisfiedParams = array_fill_keys(array_keys($sqlPattern->getNamedPlaceholderMap()), true);
    }

    /**
     * Sets the value of a parameter in the SQL pattern.
     *
     * @param string|int $nameOrPosition name of the named parameter, or (zero-based) position of the positional
     *                                     parameter, respectively
     * @param mixed $value value of the parameter;
     *                     if the parameter is specified explicitly with its type, <tt>$value</tt> must correspond to
     *                       the type;
     *                     otherwise, the type of the parameter (and thus the conversion to be used) is inferred from
     *                       the type of <tt>$value</tt>
     * @return $this
     * @throws \InvalidArgumentException when the SQL pattern has no parameter of a given name or position
     */
    public function setParam($nameOrPosition, $value) : self
    {
        if (isset($this->unsatisfiedParams[$nameOrPosition])) {
            unset($this->unsatisfiedParams[$nameOrPosition]);
        }
        elseif (!array_key_exists($nameOrPosition, $this->params)) {
            throw new \InvalidArgumentException("The SQL pattern does not have parameter '$nameOrPosition'");
        }

        $this->params[$nameOrPosition] = $value;
        return $this;
    }

    /**
     * Sets values of several parameters in the SQL pattern.
     *
     * @param array $paramMap map: parameter name (or zero-based position) => parameter value
     * @return $this
     */
    public function setParams($paramMap) : self
    {
        foreach ($paramMap as $nameOrPosition => $value) {
            $this->setParam($nameOrPosition, $value);
        }
        return $this;
    }

    public function getSqlPattern() : SqlPattern
    {
        return $this->sqlPattern;
    }

    /**
     * @return array map: parameter name or zero-based position => parameter value
     */
    public function getParams() : array
    {
        return $this->params;
    }

    /**
     * @param ITypeDictionary $typeDictionary
     * @return string
     * @throws InvalidStateException when values for one or more named parameters has not been set
     * @throws UndefinedTypeException when some of the types used in the pattern are not defined
     */
    public function toSql(ITypeDictionary $typeDictionary) : string
    {
        if ($this->unsatisfiedParams) {
            $names = array_keys($this->unsatisfiedParams);
            if (count($names) == 1) {
                $msg = sprintf('Value for parameter "%s" has not been set.', $names[0]);
            }
            else {
                $msg = sprintf(
                    'Values for parameters %s and "%s" have not been set.',
                    array_map(function ($s) { return "\"$s\""; }, array_slice($names, 0, -1))
                );
            }
            throw new InvalidStateException($msg);
        }

        $gen = $this->sqlPattern->generateSql();
        while ($gen->valid()) {
            /** @var SqlPatternPlaceholder $placeholder */
            $placeholder = $gen->current();
            assert(
                array_key_exists($placeholder->getNameOrPosition(), $this->params),
                new NoDataException("Value for parameter {$placeholder->getNameOrPosition()} not set.")
            );

            $value = $this->params[$placeholder->getNameOrPosition()];

            if ($placeholder->getTypeName() !== null) {
                $typeName = $placeholder->getTypeName();
                if (!$placeholder->isTypeNameQuoted()) {
                    $typeName = mb_strtolower($typeName); // OPT: SqlPatternPlaceholder might also store the lower-case name, which might be cached
                }
                $schemaName = $placeholder->getSchemaName();
                if ($schemaName !== null) {
                    if (!$placeholder->isSchemaNameQuoted()) {
                        $schemaName = mb_strtolower($schemaName); // OPT: SqlPatternPlaceholder might also store the lower-case name, which might be cached
                    }
                }
                elseif ($placeholder->isTypeNameQuoted()) {
                    $schemaName = false;
                }

                $converter = $typeDictionary->requireTypeByName($typeName, $schemaName);
            }
            else {
                $converter = $typeDictionary->requireTypeByValue($value);
            }
            $serializedValue = $converter->serializeValue($value);

            $gen->send($serializedValue);
        }

        return $gen->getReturn();
    }
}
