<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Type\ITotallyOrderedType;

class LtreeTotallyOrderedType extends LtreeType implements ITotallyOrderedType
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    public function parseValue(string $extRepr)
    {
        $labels = explode('.', $extRepr);
        return LtreeComparable::fromArray($labels);
    }
}
