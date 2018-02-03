<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Exception\UnsupportedException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * A special class used for any PostgreSQL range type the subtype of which is not marked as {@link ITotallyOrderedType}.
 *
 * Objects of this class only serve as null-objects. Any operation on `UnorderedRangeType` objects, even with `null`
 * values, results in an {@link \Ivory\Exception\UnsupportedException} being thrown.
 */
class UnorderedRangeType extends BaseType
{
    public function parseValue(string $extRepr)
    {
        throw new UnsupportedException(
            "{$this->getSchemaName()}.{$this->getName()} cannot be used - its subtype is not parsed by a " .
            ITotallyOrderedType::class
        );
    }

    public function serializeValue($val): string
    {
        throw new UnsupportedException(
            "{$this->getSchemaName()}.{$this->getName()} cannot be used - its subtype is not parsed by a " .
            ITotallyOrderedType::class
        );
    }
}
