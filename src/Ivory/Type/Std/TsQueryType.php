<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\TextSearchQuery;

/**
 * Text-search query.
 *
 * Represented as a {@link \Ivory\Value\TextSearchQuery} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-textsearch.html#DATATYPE-TSQUERY
 */
class TsQueryType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return TextSearchQuery::fromString($str);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TextSearchQuery) {
            $val = TextSearchQuery::fromString($val);
        }

        return "'" . strtr($val->toString(), ["'" => "''"]) . "'";
    }
}
