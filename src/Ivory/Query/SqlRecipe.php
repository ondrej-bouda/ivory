<?php
namespace Ivory\Query;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\NoDataException;
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
 * The placeholders use the following syntax in the SQL pattern:
 * <pre>
 * %[type][:name]
 * </pre>
 * where:
 * - `name` is the name of the parameter (if not specified, the parameter is treated as a positional parameter); and
 * - `type` is an explicit type specification, governing how the value given for the parameter will be encoded to the
 *   SQL string. If the type is not given, it is inferred from the actual data type of the parameter value.
 *
 * Both `name` and `type` may consist of one or more letters, digits and underscore characters. Neither `name` nor
 * `type` may start with a digit, though; especially, the name may not be a number - referring to positional arguments
 * is not supported. Moreover, dots may be used in `type` specification, although only inside the string, not as a
 * leading or trailing character. Finally, the `type` specification may be ended with a pair of square brackets,
 * indicating an array type. (Multiple pairs of brackets are accepted, although these are treated the same as a mere one
 * bracket pair, consistently with what PostgreSQL does.)
 * - examples of valid names: `tbl`, `person_id`, `p1`;
 * - examples of valid type specifications: `s`, `int_singleton`, `t1`, `public.planet`, `public.planet[]`, `int[][]`.
 * Regarding the square brackets, note, however, that only empty pairs of brackets are recognized as part of the type
 * specification. An array placeholder immediately followed by a subscript works as expected: e.g.,
 * `SELECT %bigint[][2]` selects the item under index 2 from the provided array.
 *
 * Note that names of available types, as well as rules for inferring the type automatically, are defined by the
 * {@link Ivory\Type\TypeDictionary} used by the connection for which the recipe will be serialized to an SQL string.
 * The standard Ivory deployment registers all types defined in the connected-to database under their fully qualified
 * names (e.g., `public.sometype`) and also under their names (e.g., `text`; in case multiple same-named types are
 * defined in several schemas, the one from the schema preferred by a configuration is used [TODO: specify the configuration]).
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
        $overallRawSql = '';
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
                    throw new \InvalidArgumentException("Invalid type of $ord fragment. Maybe it is a misplaced parameter value?");
                }
            }

            // add to the overall pattern
            if ($argsProcessed > 0) {
                $overallRawSql .= ' ';
            }
            $rawSqlOffset = strlen($overallRawSql);
            $overallRawSql .= $curFragment->getRawSql();
            foreach ($curFragment->getPositionalPlaceholders() as $plcHdr) {
                $overallPlcHdr = new SqlPatternPlaceholder(
                    $rawSqlOffset + $plcHdr->getOffset(),
                    count($overallPosPlaceholders),
                    $plcHdr->getTypeName()
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
                        $rawSqlOffset + $plcHdr->getOffset(),
                        $name,
                        $plcHdr->getTypeName()
                    );
                    $overallNamedPlaceholderMap[$name][] = $overallPlcHdr;
                }
            }

            // values of parameters
            $posParams = array_slice($fragmentsAndPositionalParams, $argsProcessed, $curFragment->getPositionalPlaceholders());
            if (count($posParams) == count($curFragment->getPositionalPlaceholders())) {
                $overallPosParams = array_merge($overallPosParams, $posParams);
            }
            else {
                $ord = StringUtils::englishOrd($curFragmentNum);
                throw new \InvalidArgumentException("Not enough positional parameters for the $ord fragment");
            }

            $curFragmentNum++;
            $argsProcessed += count($posParams);
        } while ($argsProcessed < count($fragmentsAndPositionalParams));

        $overallPattern = new SqlPattern($overallRawSql, $overallPosPlaceholders, $overallNamedPlaceholderMap);

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
     */
    public function getSql(ITypeDictionary $typeDictionary) : string
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
                $converter = $typeDictionary->requireTypeByName($placeholder->getTypeName());
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
