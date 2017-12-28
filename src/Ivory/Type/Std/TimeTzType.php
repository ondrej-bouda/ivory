<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\TimeTz;

/**
 * Timezone-aware time of day.
 *
 * Represented as a {@link \Ivory\Value\TimeTz} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-datetime.html
 */
class TimeTzType extends BaseType implements ITotallyOrderedType
{
    use TotallyOrderedByPhpOperators;


    public function parseValue(string $str)
    {
        return TimeTz::fromString($str);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TimeTz) {
            if ($val instanceof \DateTimeInterface) {
                $val = TimeTz::fromDateTime($val);
            } elseif (is_numeric($val)) {
                $val = TimeTz::fromUnixTimestamp($val);
            } elseif (is_string($val)) {
                try {
                    $val = TimeTz::fromString($val);
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
