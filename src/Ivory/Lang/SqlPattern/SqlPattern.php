<?php
namespace Ivory\Lang\SqlPattern;

/**
 * Representation of an SQL pattern.
 */
class SqlPattern
{
    private $rawSql;
    private $positionalPlaceholders;
    private $namedPlaceholderMap;
    /** @var SqlPatternPlaceholder[] */
    private $placeholderSequence;

    /**
     * @param string $rawSql raw SQL parsed from the described SQL pattern; this is the pattern with removed
     *                         placeholders and unescaped <tt>%%</tt> sequences
     * @param SqlPatternPlaceholder[] $positionalPlaceholders
     *                                  list of positional placeholders, in order of appearance, used in the described
     *                                    SQL pattern
     * @param SqlPatternPlaceholder[][] $namedPlaceholderMap
     *                                  map of named placeholders used in the described SQL pattern: name => list of all
     *                                    placeholders (in order of appearance) referring to the parameter name
     */
    public function __construct(string $rawSql, array $positionalPlaceholders, array $namedPlaceholderMap)
    {
        assert(
            !$positionalPlaceholders || array_keys($positionalPlaceholders) == range(0, count($positionalPlaceholders) - 1),
            new \InvalidArgumentException('$positionalPlaceholders array is not a list - keys do not form a sequence')
        );

        $this->rawSql = $rawSql;
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
     * Returns the raw SQL parsed from the described SQL pattern. This is the pattern with removed placeholders and
     * unescaped `%%` sequences.
     *
     * Parameter values must be inserted in this string to form a valid SQL statement.
     * {@link SqlPatternPlaceholder::getOffset()} tells the appropriate offset.
     */
    public function getRawSql() : string
    {
        return $this->rawSql;
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
     * Fills gaps in the raw SQL with given SQL strings to form a complete SQL string.
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
            $result .= substr($this->rawSql, $offset, $plcHld->getOffset() - $offset) . $val;
            $offset = $plcHld->getOffset();
        }
        $result .= substr($this->rawSql, $offset);

        return $result;
    }
}
