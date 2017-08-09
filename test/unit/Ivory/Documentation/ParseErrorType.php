<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Type\Postgresql\CompositeType;
use Ivory\Value\Composite;

class ParseErrorType extends CompositeType
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function constructCompositeValue(array $valueMap): Composite
    {
        return new ParseError($valueMap['file'], $valueMap['line'], $valueMap['message']);
    }
}
