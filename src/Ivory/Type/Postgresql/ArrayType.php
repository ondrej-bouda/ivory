<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Exception\InternalException;
use Ivory\Exception\ParseException;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\IType;
use Ivory\Type\TypeBase;

/**
 * Array type object.
 *
 * Note that arrays in PHP and PostgreSQL are completely different beasts. In PHP, "arrays" are in fact sorted hash maps
 * with string or integer keys, or a mixture of both, having no restrictions on the elements. In PostgreSQL, arrays are
 * much closer to other programming languages: all the elements must be of the same type and dimension (i.e.,
 * multidimensional arrays must be "rectangular") and are indexed using a continuous sequence of integers.
 *
 * Moreover, PHP arrays are zero-based by default, whereas PostgreSQL defaults to one-based arrays; in both, the bounds
 * may be explicitly specified, however.
 *
 * The array type object has two modes: *strict* and *plain*. Simply said, the strict mode converts the arrays including
 * the element indexes, whereas the plain mode ignores array indexes completely. *Strict mode is the default.*
 *
 * **Restrictions on values to be converted:**
 * - an array type object always supports just one type of array elements - the one for which the object was created;
 * - when serializing a PHP array to PostgreSQL, the array is refused if it is invalid for PostgreSQL (i.e., if the
 *   elements are of different types or dimensions, or - in the strict mode - if the array uses string keys or has gaps
 *   within the keys).
 *
 * **Behaviour according to the mode:**
 * * In the strict mode, the type object does not rebase the elements - the arrays are converted as is, including the
 *   bounds. However, when serializing a PHP array to PostgreSQL, the array gets sorted by its keys, i.e., the original
 *   order gets lost.
 * * In the plain mode, the type object merely iterates through the array and puts each item in the result, ignoring the
 *   array keys completely. The elements are not sorted by their keys. When converting arrays from PostgreSQL to PHP,
 *   zero-based arrays are created. Conversely, arrays from PHP are converted to one-based PostgreSQL arrays.
 *
 * @see https://www.postgresql.org/docs/11/arrays.html
 */
class ArrayType extends TypeBase implements ITotallyOrderedType
{
    private $elemType;
    private $delim;
    private $elemNeedsQuotesRegex;
    private $ignoreIndexes = false;

    public function __construct(IType $elemType, string $delimiter)
    {
        parent::__construct(
            $elemType->getSchemaName(),
            $elemType->getName() . '[]',
            '[]'
        );

        $this->elemType = $elemType;
        $this->delim = $delimiter;
        $this->elemNeedsQuotesRegex = '~[{}\\s"\\\\' . preg_quote($delimiter, '~') . ']|^NULL$|^$~i';
    }

    public function switchToPlainMode(): void
    {
        $this->ignoreIndexes = true;
    }

    public function switchToStrictMode(): void
    {
        $this->ignoreIndexes = false;
    }

    private function throwParseException(
        string $str,
        string $errMsg = null,
        int $offset = null,
        \Exception $cause = null
    ): void {
        $elemTypeName = $this->elemType->getSchemaName() . '.' . $this->elemType->getName();
        $msg = "Value '$str' is not valid for an array of type $elemTypeName";
        if (strlen($errMsg) > 0) {
            $msg .= ": $errMsg";
        }
        throw new ParseException($msg, $offset, 0, $cause);
    }

    public function parseValue(string $extRepr)
    {
        // OPT: The parser processes any string which is legal for the PostgreSQL input. Considering it would only be
        //      used for processing output from PostgreSQL, more limited syntax might be accepted, which would be
        //      simpler and faster; namely, in the output, there are neither whitespace nor backslash-escaped
        //      characters outside of double-quoted strings.

        $strOffset = 0;

        while (isset($extRepr[$strOffset]) && ctype_space($extRepr[$strOffset])) {
            $strOffset++;
        }

        if ($extRepr[$strOffset] == '[') { // explicit bounds specification
            $decorSepPos = strpos($extRepr, '=');
            $decoration = substr($extRepr, 0, $decorSepPos);

            preg_match_all('~\[(\d+):\d+]~', $decoration, $m);
            if ($decorSepPos === false || !$m) {
                $this->throwParseException($extRepr, 'Invalid array bounds decoration');
            }
            $strOffset = $decorSepPos + 1;
            while (isset($extRepr[$strOffset]) && ctype_space($extRepr[$strOffset])) {
                $strOffset++;
            }
            $lowerBounds = $m[1];
            unset($m);
        } else {
            if (trim($extRepr) == '{}') {
                return [];
            }
            $dims = 0;
            $len = strlen($extRepr);
            for ($i = $strOffset; $i < $len; $i++) {
                if (ctype_space($extRepr[$i])) {
                    continue;
                } elseif ($extRepr[$i] == '{') {
                    $dims++;
                } else {
                    break;
                }
            }
            if ($dims == 0) {
                $this->throwParseException($extRepr, "Expected '{'", 0);
            }
            $lowerBounds = array_fill(0, $dims, 1);
        }

        if ($this->ignoreIndexes) {
            $lowerBounds = array_fill(0, count($lowerBounds), 0);
        }

        $result = [];
        $d = preg_quote($this->delim, '~');
        $elemRegex = '~
                       "(?:[^"\\\\]|\\\\.)*"        # either a double-quoted string (backslashes used for escaping)
                       |                            # or an unquoted string of characters which do not confuse the
                                                    # parser or are backslash-escaped, leading and trailing whitespace
                                                    # being ignored:
                       (?:[^"{}\s\\\\' . $d . ']|\\\\.)+      # starting with non-special, non-whitespace characters,
                       (?:\s+(?:[^"{}\\\\' . $d . ']|\\\\.)*  # optionally followed by non-special characters,
                          (?:[^"{}\s\\\\' . $d . ']|\\\\.)+   # ending again with non-special, non-whitespace characters
                       )?
                      ~x';
        preg_match_all($elemRegex, $extRepr, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $strOffset);
        if (!$matches) {
            $this->throwParseException($extRepr, 'Invalid array syntax');
        }

        $strOffset++;
        // the current dimension of the input being processed
        $dim = 0;
        // map: dimension => key under which to add the next element in the dimension
        $keys = [$dim => $lowerBounds[$dim]];
        // map: dimension => reference to the array to add the next element to at the dimension
        $refs = [$dim => &$result];

        foreach ($matches[0] as list($elem, $elemOffset)) {
            for (; $strOffset < $elemOffset; $strOffset++) {
                $c = $extRepr[$strOffset];
                if (ctype_space($c)) {
                    continue;
                }
                switch ($c) {
                    case '{':
                        $dim++;
                        $keys[$dim] = $lowerBounds[$dim];
                        $refs[$dim] = &$refs[$dim - 1][$keys[$dim - 1]];
                        $refs[$dim] = [];
                        break;
                    case '}':
                        $dim--;
                        break;
                    case $this->delim:
                        $keys[$dim]++;
                        break;
                    default:
                        $this->throwParseException($extRepr, "Unexpected array decoration character '$c'", $strOffset);
                }
            }

            try {
                $k = $keys[$dim];
                if (strcasecmp($elem, 'NULL') == 0) {
                    $refs[$dim][$k] = null;
                } else {
                    $cont = ($elem[0] == '"' ? substr($elem, 1, -1) : $elem);
                    $unEsc = preg_replace('~\\\\(.)~', '$1', $cont);
                    $refs[$dim][$k] = $this->elemType->parseValue($unEsc);
                }
            } catch (ParseException $e) {
                $this->throwParseException($extRepr, 'Error parsing the element value', $strOffset, $e);
            }
            $strOffset += strlen($elem);
        }

        return $result;
    }

    /**
     * Serializes an array to the corresponding SQL literal.
     *
     * {@inheritDoc}
     *
     * Note that, in the strict mode, the `ARRAY[1,2,3]` syntax cannot be used as it does not allow for specifying
     * custom array bounds. Instead, the string representation (e.g., `'{1,2,3}'`) is employed.
     *
     * @todo eliminate recursion, process multidimensional arrays using iteration instead
     */
    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val !== null && !is_array($val)) {
            throw new \InvalidArgumentException("Value '$val' is not valid for array type");
        }

        if ($this->ignoreIndexes) {
            $str = $this->serializeValuePlain($val);
        } else {
            $str = $this->serializeValueStrict($val, $bounds);

            $needsDimDecoration = false;
            foreach ($bounds as $b) {
                if ($b[0] != 1) {
                    $needsDimDecoration = true;
                    break;
                }
            }
            if ($needsDimDecoration) {
                $dimDec = '';
                foreach ($bounds as $b) {
                    $dimDec .= "[{$b[0]}:{$b[1]}]";
                }
                $str = "$dimDec=$str";
            }

            $str = "'$str'"; // NOTE: literal single quotes in serialized elements are already doubled
        }

        return $this->typeCastExpr($strictType, $str);
    }

    /**
     * @param array|null $val the value to serialize
     * @param int[] $dims list: for each dimension, the size of the array is mentioned
     * @param int $curDim the current dimension being processed (zero-based)
     * @param int $maxDim the maximal dimension discovered so far
     * @return string the PostgreSQL external representation of <tt>$val</tt>
     */
    private function serializeValuePlain(?array $val, array &$dims = [], int $curDim = 0, int &$maxDim = -1): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if ($curDim > $maxDim) {
            $maxDim = $curDim;
            $expSize = count($val);
            $dims[$curDim] = $expSize;
        } else {
            $expSize = $dims[$curDim];
            if ($expSize != count($val)) {
                $itemDesc = print_r($val, true);
                $msg = "The array is not rectangular: item $itemDesc contains a wrong number of elements";
                throw new \InvalidArgumentException($msg);
            }
        }

        $out = ($curDim == 0 ? 'ARRAY[' : '[');
        $first = true;
        foreach ($val as $v) {
            if ($first) {
                $first = false;
            } else {
                $out .= $this->delim;
            }

            if (is_array($v)) {
                $out .= $this->serializeValuePlain($v, $dims, $curDim + 1, $maxDim);
            } else {
                if ($curDim != $maxDim) {
                    $msg = "Some array items do not match the dimensions of the array: " . print_r($val, true);
                    throw new \InvalidArgumentException($msg);
                }
                
                $out .= $this->elemType->serializeValue($v, false);
            }
        }
        $out .= ']';

        return $out;
    }

    /**
     * @param array|null $val the value to serialize
     * @param int[][] $bounds list: for each dimension, a pair of from-to subscripts is mentioned
     * @param int $curDim the current dimension being processed (zero-based)
     * @param int $maxDim the maximal dimension discovered so far
     * @return string the PostgreSQL external representation of <tt>$val</tt>
     */
    private function serializeValueStrict(?array $val, ?array &$bounds = [], int $curDim = 0, int &$maxDim = -1): string
    {
        if ($val === null) {
            return 'NULL';
        }

        $arr = $val;
        ksort($arr);
        $keys = array_keys($arr);

        if ($curDim > $maxDim) {
            $maxDim = $curDim;
            $b = ($arr ? [$keys[0], $keys[count($keys) - 1]] : [1, 0]);
            $bounds[$curDim] = $b;
        } else {
            $b = $bounds[$curDim];
            if ($keys[0] < $b[0] || $keys[count($keys) - 1] > $b[1]) {
                $msg = "Some array subscripts do not match the bounds of the array: " . print_r($val, true);
                throw new \InvalidArgumentException($msg);
            }
        }
        if (count($arr) != ($b[1] - $b[0] + 1)) {
            $msg = "The array subscripts do not form a continuous sequence: " . print_r($val, true);
            throw new \InvalidArgumentException($msg);
        }

        $out = '{';
        $first = true;
        foreach ($arr as $v) {
            if ($first) {
                $first = false;
            } else {
                $out .= $this->delim;
            }

            if (is_array($v)) {
                $out .= $this->serializeValueStrict($v, $bounds, $curDim + 1, $maxDim);
            } else {
                if ($curDim != $maxDim) { // it should have been an array
                    $msg = "Some array items do not match the dimensions of the array: " . print_r($val, true);
                    throw new \InvalidArgumentException($msg);
                }

                if ($v === null) {
                    $valOut = 'NULL';
                } else {
                    $valOut = $this->elemType->serializeValue($v, false);
                    /* Trim the single quotes and other decoration - the value will be used inside a string literal.
                       As an optimization, doubled single quotes (meaning the literal single quote) will be preserved
                       not to undo and do the job again on the whole array.
                     */
                    if (($beg = strpos($valOut, "'")) !== false) {
                        $end = strrpos($valOut, "'");
                        if ($beg == $end) {
                            throw new InternalException("Malformed element value serialization: $valOut");
                        }
                        $valOut = substr($valOut, $beg + 1, $end - $beg - 1);
                    }
                    if (preg_match($this->elemNeedsQuotesRegex, $valOut)) {
                        $valOut = '"' . strtr($valOut, ['"' => '\\"', '\\' => '\\\\']) . '"';
                    }
                }

                $out .= $valOut;
            }
        }
        $out .= '}';

        return $out;
    }
}
