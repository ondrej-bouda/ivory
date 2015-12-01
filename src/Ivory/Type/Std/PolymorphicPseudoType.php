<?php
namespace Ivory\Type\Std;

use Ivory\Exception\InternalException;
use Ivory\Type\BaseType;

/**
 * A polymorphic type, which basically cannot be retrieved in a `SELECT` result, except for `NULL` values.
 */
class PolymorphicPseudoType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        else {
            throw new InternalException('A non-null value to be parsed for a polymorphic pseudo-type');
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        else {
            $this->throwInvalidValue($val);
        }
    }
}
