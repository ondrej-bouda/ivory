<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\TimeInterval;

/**
 * Time interval.
 *
 * Represented as a {@link \Ivory\Value\TimeInterval} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-datetime.html
 */
class IntervalType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        return TimeInterval::fromString($extRepr);
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
