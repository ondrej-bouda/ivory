<?php
namespace Ivory\Type;

use Ivory\Exception\NotImplementedException;

/**
 * Converter for arrays.
 *
 * @see http://www.postgresql.org/docs/9.4/static/arrays.html
 * @todo support explicit index bases for arrays:
 *       SELECT '[0:2]={a,b,c}'::text[],
 *              '[1:3]={a,b,c}'::text[],
 *              '[1:1][-2:-1][3:5]={{{1,2,3},{4,5,6}}}'::int[],
 *              ARRAY[ARRAY[1,4,5],NULL]::pg_catalog.int4[][]
 */
class ArrayType implements IType
{
    private $elemType;
    private $delim;

    public function __construct(INamedType $elemType, $delimiter)
    {
        $this->elemType = $elemType;
        $this->delim = $delimiter;
    }

    public function parseValue($str)
    {

        throw new NotImplementedException();
    }

    public function serializeValue($val)
    {
        return $this->performSerializeValue($val);
    }

    private function performSerializeValue($val, $curDim = 1, &$maxDim = null)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!is_array($val)) {
            throw new \InvalidArgumentException("Value '$val' is not valid for array type");
        }

        $maxDim = max($maxDim, $curDim);

        $result = 'ARRAY[';
        $first = true;
        foreach ($val as $v) {
            if ($first) {
                $first = false;
            }
            else {
                $result .= $this->delim;
            }

            if (is_array($v)) {
                $result .= $this->performSerializeValue($v, $curDim + 1, $maxDim);
            }
            elseif ($curDim != $maxDim) {
                $msg = "Some array items do not match the dimensions of the array: " . print_r($val, true);
                throw new \InvalidArgumentException($msg);
            }
            else {
                $result .= $this->elemType->serializeValue($v);
            }
        }
        $result .= ']';

        if ($curDim == 1) {
            $result .= sprintf('::%s.%s', $this->elemType->getSchemaName(), $this->elemType->getName());
            $result .= str_repeat('[]', $maxDim);
        }

        return $result;
    }
}
