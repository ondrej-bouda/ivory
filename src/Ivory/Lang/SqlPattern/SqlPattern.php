<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;

/**
 * Representation of an SQL pattern.
 *
 * The objects are immutable.
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

    /**
     * Generate SQL string from this pattern with encoded parameter values requested to be filled by the caller.
     *
     * This is a more general method to {@link fillSql()}. The reason for this method is that a single named parameter
     * may have multiple placeholders within the pattern, each with a different type specification. Therefore, each
     * occurrence might result in different encoding. This method presents the caller each placeholder, one by one, so
     * that the caller provides the encoded parameter value.
     *
     * Technically, this is achieved by returning a `\Generator`. Each placeholder is yielded as a
     * `$placeholder => &$value` pair - the key is a {@link SqlPatternPlaceholder} object describing the placeholder to
     * fill the value for, and `$value` is a reference into which the value shall be encoded. After iterating over all
     * placeholders for which a value is requested, the final SQL string may be retrieved by calling
     * {@link \Generator::getReturn()} on the generator.
     *
     * Example:
     * <pre>
     * <?php
     * $pattern = new SqlPattern(
     *     'SELECT id FROM  UNION SELECT object_id FROM log WHERE table = ',
     *     [new SqlPatternPlaceholder(15, 'tbl', 'ident'), new SqlPatternPlaceholder(62, 'tbl', 'string')],
     *     []
     * );
     * $gen = $pattern->generateSql();
     * foreach ($gen as $placeholder => &$value) {
     *     $value = ($placeholder->getTypeName() == 'string' ? "'person'" : 'person');
     * }
     * echo $gen->getReturn(); // prints "SELECT id FROM person UNION SELECT object_id FROM log WHERE table = 'person'"
     * </pre>
     *
     * @throws NoDataException if a yielded parameter does not get its encoded value
     */
    public function &generateSql() : \Generator
    {
        $result = '';
        $offset = 0;
        foreach ($this->placeholderSequence as $plcHdr) {
            $val = false;
            yield $plcHdr => $val;
            assert(
                $val !== false,
                new NoDataException(
                    "No value encoded for placeholder {$plcHdr->getNameOrPosition()} at offset {$plcHdr->getOffset()}."
                )
            );
            $result .= substr($this->rawSql, $offset, $plcHdr->getOffset() - $offset) . $val;
            $offset = $plcHdr->getOffset();
        }
        $result .= substr($this->rawSql, $offset);

        return $result;
    }
}
