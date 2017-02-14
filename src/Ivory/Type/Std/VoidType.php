<?php
namespace Ivory\Type\Std;

use Ivory\Exception\IncomparableException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Representation of the PostgreSQL void type, i.e., nothing.
 *
 * There are just two possible values accepted or returned by this converter: `null` and {@link VoidType::void()}, which
 * is an empty singleton object.
 */
class VoidType extends BaseType implements ITotallyOrderedType
{
    public static function void()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new \stdClass();
        }
        return $inst;
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        } else {
            return self::void();
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val === self::void()) {
            return "''::{$this->getSchemaName()}.{$this->getName()}";
        } else {
            $this->throwInvalidValue($val);
        }
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }
        if ($a === self::void() && $b === self::void()) {
            return true;
        }
        throw new IncomparableException('Invalid values to compare as ' . VoidType::class);
    }
}
