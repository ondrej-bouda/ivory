<?php
namespace Ivory\Type;

use Ivory\Exception\InternalException;
use Ivory\Exception\ParseException;
use Ivory\Exception\UnsupportedException;

/**
 * Converter for arrays.
 *
 * Note that arrays in PHP and PostgreSQL are completely different beasts. In PHP, "arrays" are in fact sorted hash maps
 * with string or integer keys, or a mixture of both, having no restrictions on the elements. In PostgreSQL, arrays are
 * much closer to other programming languages: all the elements must be of the same type and dimension (i.e.,
 * multidimensional arrays must be "rectangular") and are indexed using a continuous sequence of integers. Thus:
 * - an array converter always supports just one type of array elements - the one for which the converter was created;
 * - when serializing a PHP array to PostgreSQL, the array is refused if it is invalid for PostgreSQL (i.e., if it uses
 *   string keys or has gaps within the keys or the elements are of different types or dimensions);
 * - when serializing a PHP array to PostgreSQL, the array gets sorted by its keys, i.e., the original order gets lost.
 *
 * Moreover, PHP arrays are zero-based by default, whereas PostgreSQL defaults to one-based arrays; in both, the bounds
 * may be explicitly specified, however. The converter does not rebase the elements - the arrays are converted as is,
 * including the bounds.
 *
 * @see http://www.postgresql.org/docs/9.4/static/arrays.html
 */
class ArrayType implements ITotallyOrderedType, INamedType
{
    private $elemType;
    private $delim;
    private $elemNeedsQuotesRegex;

    public function __construct(INamedType $elemType, string $delimiter)
    {
        $this->elemType = $elemType;
        $this->delim = $delimiter;
        $this->elemNeedsQuotesRegex = '~[{}\\s"\\\\' . preg_quote($delimiter, '~') . ']|^NULL$|^$~i';
    }

    public function getSchemaName(): string
    {
        return $this->elemType->getSchemaName();
    }

    public function getName(): string
    {
        return $this->elemType->getName() . '[]';
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        // OPT: The parser processes any string which is legal for the PostgreSQL input. Considering it would only be
        //      used for processing output from PostgreSQL, more limited syntax might be accepted, which would be
        //      simpler and faster; namely, in the output, there are neither whitespace nor backslash-escaped
        //      characters outside of double-quoted strings.

        $strOffset = 0;

        for (; isset($str[$strOffset]) && ctype_space($str[$strOffset]); $strOffset++) {
        }

        if ($str[$strOffset] == '[') { // explicit bounds specification
            $decorSepPos = strpos($str, '=');
            $decoration = substr($str, 0, $decorSepPos);

            preg_match_all('~\[(\d+):\d+\]~', $decoration, $m);
            if ($decorSepPos === false || !$m) {
                self::throwArrayParseException($str, 'Invalid array bounds decoration');
            }
            for ($strOffset = $decorSepPos + 1; isset($str[$strOffset]) && ctype_space($str[$strOffset]); $strOffset++) {
            }
            $lowerBounds = $m[1];
            unset($m);
        } else {
            if (trim($str) == '{}') {
                return [];
            }
            $dims = 0;
            $len = strlen($str);
            for ($i = $strOffset; $i < $len; $i++) {
                if (ctype_space($str[$i])) {
                    continue;
                } elseif ($str[$i] == '{') {
                    $dims++;
                } else {
                    break;
                }
            }
            if ($dims == 0) {
                self::throwArrayParseException($str, "Expected '{'", 0);
            }
            $lowerBounds = array_fill(0, $dims, 1);
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
        preg_match_all($elemRegex, $str, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $strOffset);
        if (!$matches) {
            $this->throwParseException($str, 'Invalid array syntax');
        }

        $strOffset++;
        $dim = 0; // the current dimension of the input being processed
        $keys = [$dim => $lowerBounds[$dim]]; // map: dimension => key under which to add the next element in the dimension
        $refs = [$dim => &$result]; // map: dimension => reference to the array to add the next element to at the dimension

        foreach ($matches[0] as list($elem, $elemOffset)) {
            for (; $strOffset < $elemOffset; $strOffset++) {
                $c = $str[$strOffset];
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
                        $this->throwParseException($str, "Unexpected array decoration character '$c'", $strOffset);
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
                $this->throwParseException($str, 'Error parsing the element value', $strOffset, $e);
            }
            $strOffset += strlen($elem);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Note the `ARRAY[1,2,3]` syntax cannot be used as it does not allow for specifying custom array bounds. Instead,
     * the string representation (e.g., `'{1,2,3}'`) is employed.
     *
     * @todo eliminate recursion, process multidimensional arrays using iteration instead
     */
    public function serializeValue($val): string
    {
        $str = $this->serializeValueImpl($val, $bounds);

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

        return sprintf("'%s'::%s.%s[]",
            $str, // NOTE: literal single quotes in serialized elements are already doubled
            $this->elemType->getSchemaName(),
            $this->elemType->getName()
        );
    }

    /**
     * @param array|null $val the value to serialize
     * @param int[][] $bounds list: for each dimension, a pair of from-to subscripts is mentioned
     * @param int $curDim the current dimension being processed (zero-based)
     * @param int $maxDim the maximal dimension discovered so far
     * @return string the PostgreSQL external representation of <tt>$val</tt>
     */
    private function serializeValueImpl($val, &$bounds = [], $curDim = 0, &$maxDim = -1)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!is_array($val)) {
            throw new \InvalidArgumentException("Value '$val' is not valid for array type");
        }

        $arr = $val;
        ksort($arr);
        $keys = array_keys($arr);

        if ($maxDim < $curDim) {
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
                $out .= $this->serializeValueImpl($v, $bounds, $curDim + 1, $maxDim);
            } else {
                if ($curDim != $maxDim) {
                    $msg = "Some array items do not match the dimensions of the array: " . print_r($val, true);
                    throw new \InvalidArgumentException($msg);
                }

                if ($v === null) {
                    $valOut = 'NULL';
                } else {
                    $valOut = $this->elemType->serializeValue($v);
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

    private static function throwArrayParseException($str, $errMsg = null, $offset = null)
    {
        $msg = "Value '$str' is not valid for an array";
        if (strlen($errMsg) > 0) {
            $msg .= ": $errMsg";
        }
        throw new ParseException($msg, $offset);
    }

    private function throwParseException($str, $errMsg = null, $offset = null, $cause = null)
    {
        $elemTypeName = $this->elemType->getSchemaName() . '.' . $this->elemType->getName();
        $msg = "Value '$str' is not valid for an array of type $elemTypeName";
        if (strlen($errMsg) > 0) {
            $msg .= ": $errMsg";
        }
        throw new ParseException($msg, $offset, 0, $cause);
    }

    public function compareValues($a, $b)
    {
        if (!$this->elemType instanceof ITotallyOrderedType) {
            $elemTypeName = $this->elemType->getSchemaName() . '.' . $this->elemType->getName();
            throw new UnsupportedException("The array element type $elemTypeName is not totally ordered.");
        }

        if ($a === null || $b === null) {
            return null;
        }

        return $this->compareValuesImpl($a, $b);
    }

    private function compareValuesImpl($a, $b)
    {
        reset($b);
        foreach ($a as $av) {
            if (key($b) === null) {
                return 1;
            }
            $bv = current($b);
            next($b);
            if ($av === null) {
                if ($bv !== null) {
                    return -1;
                }
            } elseif ($bv === null) {
                return 1;
            } elseif (is_array($av)) {
                if (is_array($bv)) {
                    $comp = $this->compareValuesImpl($av, $bv);
                    if ($comp) {
                        return $comp;
                    }
                } else {
                    return 1;
                }
            } elseif (is_array($bv)) {
                return -1;
            } else {
                /** @var ITotallyOrderedType $et */
                $et = $this->elemType;
                $comp = $et->compareValues($av, $bv);
                if ($comp) {
                    return $comp;
                }
            }
        }
        if (key($b) !== null) {
            return -1;
        }

        // ties broken by the subscripts of the first item
        $aFst = $a;
        $bFst = $b;
        do {
            reset($aFst);
            reset($bFst);
            $ak = key($aFst);
            $bk = key($bFst);
            if ($ak === null && $bk === null) {
                return 0;
            } elseif ($ak === null) {
                return -1;
            } elseif ($bk === null) {
                return 1;
            } elseif (!is_numeric($ak) || !is_numeric($bk)) {
                return 0;
            } else {
                $d = $ak - $bk;
                if ($d) {
                    return $d;
                } else {
                    $aFst = current($aFst);
                    $bFst = current($bFst);
                }
            }
        } while (is_array($aFst) && is_array($bFst));

        return 0;
    }
}
