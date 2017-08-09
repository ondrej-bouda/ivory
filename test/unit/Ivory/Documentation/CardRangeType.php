<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Type\Postgresql\RangeType;
use Ivory\Value\Range;

/**
 * Type converter for an imaginary "cardrange" type.
 */
class CardRangeType extends RangeType
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function createParsedRange($lower, $upper, bool $lowerInc, bool $upperInc): Range
    {
        return CardRange::fromBounds($lower, $upper, $lowerInc, $upperInc);
    }
}
