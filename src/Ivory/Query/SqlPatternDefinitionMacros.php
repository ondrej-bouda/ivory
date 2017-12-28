<?php
declare(strict_types=1);

namespace Ivory\Query;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\NoDataException;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Lang\SqlPattern\SqlPatternPlaceholder;
use Ivory\Type\ITypeDictionary;
use Ivory\Utils\StringUtils;

trait SqlPatternDefinitionMacros
{
    /** @var SqlPattern */
    private $sqlPattern;
    /** @var array map: parameter name or position => supplied value */
    private $params;
    /** @var bool[] map: name of parameter which has not been set any value yet => <tt>true</tt> value */
    private $unsatisfiedParams;


    /**
     * Creates an SQL definition from an SQL string.
     *
     * No parameter substitution is performed on the string - it is used as is.
     *
     * @param string $sql SQL string
     * @return static
     */
    public static function fromSql(string $sql): self
    {
        $sqlPattern = new SqlPattern($sql, [], []);
        return new static($sqlPattern, []);
    }

    /**
     * Creates a new definition from an SQL pattern.
     *
     * Values for all positional parameters required by the pattern must be given.
     *
     * Example:
     * <code>
     * // relation definition given by "SELECT * FROM person WHERE role = 4 AND email = 'john@doe.com'"
     * $relDef = SqlRelationDefinition::fromPattern(
     *     'SELECT * FROM person WHERE role = %i AND email = %s',
     *     4, 'john@doe.com'
     * );
     *
     * // command defined by "DELETE FROM mytable WHERE id < 100"
     * $cmd = SqlCommand::fromPattern(
     *     'DELETE FROM %ident WHERE id < %i',
     *     'mytable', 100
     * );
     * </code>
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
    public static function fromPattern($sqlPattern, ...$positionalParameters): self
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
     * Creates an SQL definition from one or more fragments, each with its own positional parameters.
     *
     * Each fragment must be immediately followed by values for all positional parameters it requires. Then, another
     * fragment may follow. As the very last argument, a map of values for named parameters may optionally be given (or
     * {@link setParams()} may be used to set them later).
     *
     * The fragments get concatenated to form the resulting SQL pattern. A single space is added between each two
     * fragments the former of which ends with a non-whitespace character and the latter of which starts with a
     * non-whitespace character.
     *
     * Named parameters are shared among fragments. In other words, if two fragments use the same named parameter,
     * specifying the parameter by {@link setParam()} will substitute the same value to both fragments.
     *
     * Example:
     * <code>
     * // relation definition given by "SELECT * FROM person WHERE role = 4 AND email = 'john@doe.com'"
     * $relDef = SqlRelationDefinition::fromFragments(
     *     'SELECT * FROM person WHERE role = %i', 4, 'AND email = %s', 'john@doe.com'
     * );
     *
     * // command defined by "DELETE FROM mytable WHERE id < 100"
     * $cmd = SqlCommand::fromFragments(
     *     'DELETE FROM %ident', 'mytable',
     *     'WHERE id < %i', 100
     * );
     * </code>
     *
     * Performance considerations: parsing the SQL pattern, if given as a string, is done by the parser obtained by
     * {@link \Ivory\Ivory::getSqlPatternParser()}. Depending on Ivory configuration, the parser will cache the results
     * and reuse them for the same pattern next time.
     *
     * @internal Ivory design note: The single space added between each two fragments aspires to be more practical than
     * a mere concatenation, which would require the user to specify spaces where the next fragment immediately
     * continued with the query. After all, the method has ambitions to at least partly understand the user wants to
     * compose an SQL query from several parts, thus, it is legitimate the query is modified appropriately.
     *
     * @param string|SqlPattern $fragment
     * @param array ...$fragmentsAndParamValues
     *                                  further fragments (each of which is either a <tt>string</tt> or an
     *                                    {@link SqlPattern} object) and values of their parameters;
     *                                  the very last argument may be a map of values for named parameters to set
     *                                    immediately
     *
     * @return static
     * @throws \InvalidArgumentException when any fragment is not followed by the exact number of parameter values it
     *                                     requires
     */
    public static function fromFragments($fragment, ...$fragmentsAndParamValues): self
    {
        $overallSqlTorso = '';
        $overallPosPlaceholders = [];
        $overallNamedPlaceholderMap = [];
        $overallPosParams = [];

        $namedParamValues = [];

        $curFragment = $fragment;
        $curFragmentNum = 1;
        $argsProcessed = 0;
        $overallEndsWithPlaceholder = false;
        do {
            // process the fragment
            if (!$curFragment instanceof SqlPattern) {
                if (is_string($curFragment)) {
                    $parser = \Ivory\Ivory::getSqlPatternParser();
                    $curFragment = $parser->parse($curFragment);
                } elseif (
                    is_iterable($curFragment) &&
                    $argsProcessed > 0 &&
                    !array_key_exists($argsProcessed, $fragmentsAndParamValues)
                ) {
                    $namedParamValues = $curFragment;
                    break;
                } else {
                    $ord = StringUtils::englishOrd($curFragmentNum);
                    throw new \InvalidArgumentException("Invalid type of $ord fragment. Isn't it a misplaced parameter value?");
                }
            }

            // add to the overall pattern
            $curSqlTorso = $curFragment->getSqlTorso();
            $curPosParams = $curFragment->getPositionalPlaceholders();
            $curNamedParams = $curFragment->getNamedPlaceholderMap();
            $fragmentStartsWithPlaceholder = (
                ($curPosParams ? $curPosParams[0]->getOffset() == 0 : false)
                ||
                ($curNamedParams ? current($curNamedParams)[0]->getOffset() == 0 : false)
            );
            if (($overallEndsWithPlaceholder || preg_match('~[^ \t\r\n]$~uD', $overallSqlTorso)) &&
                ($fragmentStartsWithPlaceholder || preg_match('~^[^ \t\r\n]~u', $curSqlTorso)))
            {
                $overallSqlTorso .= ' ';
            }
            $sqlTorsoOffset = strlen($overallSqlTorso);
            $sqlTorsoLen = strlen($curSqlTorso);
            $overallSqlTorso .= $curSqlTorso;
            $overallEndsWithPlaceholder = false;
            foreach ($curPosParams as $plcHdr) {
                $overallPlcHdr = new SqlPatternPlaceholder(
                    $sqlTorsoOffset + $plcHdr->getOffset(),
                    count($overallPosPlaceholders),
                    $plcHdr->getTypeName(),
                    $plcHdr->isTypeNameQuoted(),
                    $plcHdr->getSchemaName(),
                    $plcHdr->isSchemaNameQuoted()
                );
                $overallPosPlaceholders[] = $overallPlcHdr;
                $overallEndsWithPlaceholder = ($overallEndsWithPlaceholder || $plcHdr->getOffset() == $sqlTorsoLen);
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
                    $overallEndsWithPlaceholder = ($overallEndsWithPlaceholder || $plcHdr->getOffset() == $sqlTorsoLen);
                }
            }

            // values of parameters
            $plcHdrCnt = count($curPosParams);
            $posParams = array_slice($fragmentsAndParamValues, $argsProcessed, $plcHdrCnt);
            if (count($posParams) == $plcHdrCnt) {
                $overallPosParams = array_merge($overallPosParams, $posParams);
            } else {
                $ord = StringUtils::englishOrd($curFragmentNum);
                throw new \InvalidArgumentException("Not enough positional parameters for the $ord fragment");
            }

            $curFragmentNum++;
            $argsProcessed += count($posParams);

            $curFragment =& $fragmentsAndParamValues[$argsProcessed];
            $argsProcessed++;
        } while (isset($curFragment));

        $overallPattern = new SqlPattern($overallSqlTorso, $overallPosPlaceholders, $overallNamedPlaceholderMap);

        $def = new static($overallPattern, $overallPosParams);
        $def->setParams($namedParamValues);
        return $def;
    }

    final private function __construct(SqlPattern $sqlPattern, array $positionalParameters)
    {
        $this->sqlPattern = $sqlPattern;
        $this->params = $positionalParameters;
        $this->unsatisfiedParams = array_fill_keys(array_keys($sqlPattern->getNamedPlaceholderMap()), true);
    }

    public function setParam($nameOrPosition, $value)
    {
        if (isset($this->unsatisfiedParams[$nameOrPosition])) {
            unset($this->unsatisfiedParams[$nameOrPosition]);
        } elseif (!array_key_exists($nameOrPosition, $this->params)) {
            throw new \InvalidArgumentException("The SQL pattern does not have parameter '$nameOrPosition'");
        }

        $this->params[$nameOrPosition] = $value;
        return $this;
    }

    public function setParams(iterable $paramMap)
    {
        foreach ($paramMap as $nameOrPosition => $value) {
            $this->setParam($nameOrPosition, $value);
        }
        return $this;
    }

    public function getSqlPattern(): SqlPattern
    {
        return $this->sqlPattern;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function toSql(ITypeDictionary $typeDictionary, array $namedParameterValues = []): string
    {
        $unsatisfiedParams = array_diff_key($this->unsatisfiedParams, $namedParameterValues);

        if ($unsatisfiedParams) {
            $names = array_keys($unsatisfiedParams);
            if (count($names) == 1) {
                $msg = sprintf('Value for parameter "%s" has not been set.', $names[0]);
            } else {
                $msg = sprintf(
                    'Values for parameters %s and "%s" have not been set.',
                    implode(', ', array_map(function ($s) { return "\"$s\""; }, array_slice($names, 0, -1))),
                    $names[count($names) - 1]
                );
            }
            throw new InvalidStateException($msg);
        }

        $gen = $this->sqlPattern->generateSql();
        while ($gen->valid()) {
            /** @var SqlPatternPlaceholder $placeholder */
            $placeholder = $gen->current();
            $nameOrPos = $placeholder->getNameOrPosition();

            if (array_key_exists($nameOrPos, $namedParameterValues)) {
                $value = $namedParameterValues[$nameOrPos];
            } else {
                assert(
                    array_key_exists($placeholder->getNameOrPosition(), $this->params),
                    new NoDataException("Value for parameter {$placeholder->getNameOrPosition()} not set.")
                );
                $value = $this->params[$nameOrPos];
            }

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
                } elseif ($placeholder->isTypeNameQuoted()) {
                    $schemaName = false;
                }

                $serializer = null;
                if ($schemaName === null) {
                    $serializer = $typeDictionary->getValueSerializer($typeName);
                }
                if ($serializer === null) {
                    $serializer = $typeDictionary->requireTypeByName($typeName, $schemaName);
                }
            } else {
                $serializer = $typeDictionary->requireTypeByValue($value);
            }
            $serializedValue = $serializer->serializeValue($value);

            $gen->send($serializedValue);
        }

        $sql = $gen->getReturn();
        assert(is_string($sql));
        return $sql;
    }
}
