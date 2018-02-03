<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Time;

/**
 * Time of day.
 *
 * Represented as a {@link \Ivory\Value\Time} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 */
class TimeType extends BaseType implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        return Time::fromString($extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Time) {
            if ($val instanceof \DateTimeInterface) {
                $val = Time::fromDateTime($val);
            } elseif (is_numeric($val)) {
                $val = Time::fromUnixTimestamp($val);
            } elseif (is_string($val)) {
                try {
                    $val = Time::fromString($val);
                } catch (\InvalidArgumentException $e) {
                    throw $this->invalidValueException($val, $e);
                } catch (\OutOfRangeException $e) {
                    throw $this->invalidValueException($val, $e);
                }
            } else {
                throw $this->invalidValueException($val);
            }
        }

        return "'" . $val->toString() . "'";
    }
}
