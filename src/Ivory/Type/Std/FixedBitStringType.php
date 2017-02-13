<?php
namespace Ivory\Type\Std;

/**
 * Fixed-length bit string.
 *
 * Represented as a {@link \Ivory\Value\FixedBitString} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-bit.html
 */
class FixedBitStringType extends BitStringType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return \Ivory\Value\FixedBitString::fromString($str);
        }
    }
}
