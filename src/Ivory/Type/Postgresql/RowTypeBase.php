<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Exception\ParseException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * A common base for types holding a tuple of values.
 *
 * @see https://www.postgresql.org/docs/11/rowtypes.html
 */
abstract class RowTypeBase extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        if ($extRepr == '()') {
            return $this->makeParsedValue([]);
        }

        $strLen = strlen($extRepr);
        if ($extRepr[0] != '(') {
            throw new ParseException('Composite value not enclosed in parentheses', 0);
        }
        if ($extRepr[$strLen - 1] != ')') {
            throw new ParseException('Composite value not enclosed in parentheses', $strLen - 1);
        }
        $strOffset = 1;

        $attRegex = '~
		              "(?:[^"\\\\]|""|\\\\.)*"      # either a double-quoted string (backslashes used for escaping, or
		                                            # double quotes doubled for a single double-quote character),
                      |                             # or an unquoted string of characters which do not confuse the
                      (?:[^"()\\\\,]|\\\\.)+        # parser or are backslash-escaped
		             ~x';
        preg_match_all($attRegex, $extRepr, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $strOffset);

        $atts = [];
        $pos = 0;
        foreach ($matches[0] as list($att, $attOffset)) {
            for (; $strOffset < $attOffset; $strOffset++) {
                if ($extRepr[$strOffset] == ',') {
                    $atts[] = null;
                    $pos++;
                } else {
                    throw new ParseException("Expecting ',' instead of '{$extRepr[$strOffset]}'", $strOffset);
                }
            }
            $cont = ($att[0] == '"' ? substr($att, 1, -1) : $att);
            $atts[] = $this->parseItem($pos, preg_replace(['~\\\\(.)~', '~""~'], ['$1', '"'], $cont));
            $pos++;
            $strOffset += strlen($att);
            if (!($extRepr[$strOffset] == ',' || ($extRepr[$strOffset] == ')' && $strOffset == $strLen - 1))) {
                throw new ParseException("Expecting ',' instead of '{$extRepr[$strOffset]}'", $strOffset);
            }
            $strOffset++;
        }
        for (; $strOffset < $strLen; $strOffset++) {
            if ($extRepr[$strOffset] == ',' || ($extRepr[$strOffset] == ')' && $strOffset == $strLen - 1)) {
                $atts[] = null;
                $pos++;
            } else {
                throw new ParseException("Expecting ',' instead of '{$extRepr[$strOffset]}'", $strOffset);
            }
        }

        return $this->makeParsedValue($atts);
    }

    /**
     * Parse a row item, read from a given position in the row, from its external representation.
     *
     * @param int $pos
     * @param string $itemExtRepr
     * @return mixed
     */
    abstract protected function parseItem(int $pos, string $itemExtRepr);

    /**
     * Make the final value out of a list of parsed items of the row.
     *
     * @param array $items list of parsed items, in the order from the parsed row
     * @return mixed
     */
    abstract protected function makeParsedValue(array $items);

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        $res = '(';
        $cnt = $this->serializeBody($res, $val);
        $res .= ')';
        if ($cnt < 2) {
            $res = 'ROW' . $res;
        }
        return $res;
    }

    abstract protected function serializeBody(string &$result, $value): int;
}
