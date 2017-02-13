<?php
namespace Ivory\Type\Std;

/**
 * Variable-length bit string.
 *
 * Represented as a {@link \Ivory\Value\VarBitString} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-bit.html
 */
class VarBitStringType extends BitStringType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return \Ivory\Value\VarBitString::fromString($str);
        }
    }
}
