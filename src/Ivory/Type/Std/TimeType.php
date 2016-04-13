<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TotallyOrderedByPhpOperators;
use Ivory\Value\Time;

class TimeType extends BaseType implements ITotallyOrderedType
{
    use TotallyOrderedByPhpOperators;


    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        return Time::fromString($str);
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Time) {
            if ($val instanceof \DateTimeInterface) {
                $val = Time::fromDateTime($val);
            }
            elseif (is_numeric($val)) {
                $val = Time::fromTimestamp($val);
            }
            elseif (is_string($val)) {
                try {
                    $val = Time::fromString($val);
                }
                catch (\InvalidArgumentException $e) {
                    $this->throwInvalidValue($val, $e);
                }
                catch (\OutOfRangeException $e) {
                    $this->throwInvalidValue($val, $e);
                }
            }
            else {
                $this->throwInvalidValue($val);
            }
        }

        return "'" . $val->toString() . "'";
    }
}
