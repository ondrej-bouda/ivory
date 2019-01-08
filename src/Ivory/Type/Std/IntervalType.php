<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\TimeInterval;

/**
 * Time interval.
 *
 * Represented as a {@link \Ivory\Value\TimeInterval} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-datetime.html
 */
class IntervalType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        return TimeInterval::fromString($extRepr);
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof TimeInterval) {
            if (is_array($val)) {
                $val = TimeInterval::fromParts($val);
            } else {
                $val = TimeInterval::fromString($val);
            }
        }

        return $this->indicateType($forceType, "'" . $val->toIsoString() . "'");
    }
}
