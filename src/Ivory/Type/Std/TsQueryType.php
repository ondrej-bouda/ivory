<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Value\TextSearchQuery;

/**
 * Text-search query.
 *
 * Represented as a {@link \Ivory\Value\TextSearchQuery} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-textsearch.html#DATATYPE-TSQUERY
 */
class TsQueryType extends TypeBase
{
    public function parseValue(string $extRepr)
    {
        return TextSearchQuery::fromString($extRepr);
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof TextSearchQuery) {
            $val = TextSearchQuery::fromString($val);
        }

        return $this->indicateType($forceType, Types::serializeString($val->toString()));
    }
}
