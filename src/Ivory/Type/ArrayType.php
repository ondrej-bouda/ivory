<?php
namespace Ivory\Type;

use Ivory\Exception\InternalException;
use Ivory\Exception\NotImplementedException;

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
class ArrayType implements IType
{
    private $elemType;
    private $delim;
    private $elemNeedsQuotesRegex;

    public function __construct(INamedType $elemType, $delimiter)
    {
        $this->elemType = $elemType;
        $this->delim = $delimiter;
        $this->elemNeedsQuotesRegex = '~[{}\\s"\\\\' . preg_quote($delimiter, '~') . ']|^NULL$|^$~i';
    }

    public function parseValue($str)
    {
        throw new NotImplementedException(); // TODO
    }

    /**
     * {@inheritdoc}
     *
     * Note the `ARRAY[1,2,3]` syntax cannot be used as it does not allow for specifying custom array bounds. Instead,
     * the string representation (e.g., `'{1,2,3}'`) is employed.
     */
    public function serializeValue($val)
    {
        return $this->performSerializeValue($val);
    }

    /**
     * @param array|null $val the value to serialize
     * @param int $curDim the current dimension being processed (zero-based)
     * @param int $maxDim the maximal dimension discovered so far
     * @param int[][] $bounds list: for each dimension, a pair of from-to subscripts is mentioned
     * @return string the PostgreSQL external representation of <tt>$val</tt>
     */
    private function performSerializeValue($val, $curDim = 0, &$maxDim = -1, &$bounds = [])
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
        }
        else {
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
            }
            else {
                $out .= $this->delim;
            }

            if (is_array($v)) {
                $out .= $this->performSerializeValue($v, $curDim + 1, $maxDim, $bounds);
            }
            else {
                if ($curDim != $maxDim) {
                    $msg = "Some array items do not match the dimensions of the array: " . print_r($val, true);
                    throw new \InvalidArgumentException($msg);
                }

                if ($v === null) {
                    $valOut = 'NULL';
                }
                else {
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

        if ($curDim == 0) {
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
                $out = "$dimDec=$out";
            }

            $out = sprintf("'%s'::%s.%s[]",
                $out, // NOTE: literal single quotes in serialized elements are already doubled
                $this->elemType->getSchemaName(),
                $this->elemType->getName()
            );
        }

        return $out;
    }
}
