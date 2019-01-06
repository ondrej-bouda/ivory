<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\BaseType;
use Ivory\Value\TextSearchQuery;

/**
 * Text-search query.
 *
 * Represented as a {@link \Ivory\Value\TextSearchQuery} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-textsearch.html#DATATYPE-TSQUERY
 */
class TsQueryType extends BaseType
{
    public function parseValue(string $extRepr)
    {
        return TextSearchQuery::fromString($extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TextSearchQuery) {
            $val = TextSearchQuery::fromString($val);
        }

        return Types::serializeString($val->toString());
    }
}
