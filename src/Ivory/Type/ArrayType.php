<?php
namespace Ivory\Type;

use Ivory\Exception\InternalException;
use Ivory\Exception\NotImplementedException;

/**
 * Converter for arrays.
 *
 * Note that in PHP, an array without explicit bounds is indexed from 0, while in PostgreSQL, arrays are 1-based by
 * default. The converter maintains the array as is, i.e., it does not try to convert 1-based arrays to 0-based, or vice
 * versa. Note that both PHP and PostgreSQL allow array literals to explicitly mention the array bounds, which this
 * converter takes use of.
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

        throw new NotImplementedException();
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
