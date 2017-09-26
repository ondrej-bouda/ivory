<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\TimeInterval;

/**
 * Time interval.
 *
 * Represented as a {@link \Ivory\Value\TimeInterval} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 */
class IntervalType extends BaseType implements ITotallyOrderedType
{
    use TotallyOrderedByPhpOperators;


    public function parseValue(string $str)
    {
        return TimeInterval::fromString($str);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TimeInterval) {
            if (is_array($val)) {
                $val = TimeInterval::fromParts($val);
            } else {
                $val = TimeInterval::fromString($val);
            }
        }

        return "'" . $val->toIsoString() . "'";
    }
}
