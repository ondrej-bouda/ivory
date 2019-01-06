<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\FixedBitString;

/**
 * Fixed-length bit string.
 *
 * Represented as a {@link \Ivory\Value\FixedBitString} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-bit.html
 */
class FixedBitStringType extends BitStringType
{
    public function parseValue(string $extRepr)
    {
        return FixedBitString::fromString($extRepr);
    }
}
