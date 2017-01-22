<?php
namespace Ivory\Type\Ivory;

use Ivory\Exception\UndefinedOperationException;
use Ivory\Type\IType;

/**
 * A common base for internal type converters only intended to serialize values in SQL patterns.
 */
abstract class PatternTypeBase implements IType
{
    public function parseValue($str)
    {
        throw new UndefinedOperationException('This converter is not supposed to parse values, only serialize them.');
    }
}
