<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Exception\IncomparableException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;

/**
 * Representation of the PostgreSQL void type, i.e., nothing.
 *
 * There are just two possible values accepted or returned by this type object: `null` and {@link VoidType::void()},
 * which is an empty singleton object.
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

    public function parseValue(string $str)
    {
        return self::void();
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val === self::void()) {
            return "''::{$this->getSchemaName()}.{$this->getName()}";
        } else {
            throw $this->invalidValueException($val);
        }
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        if ($a === self::void() && $b === self::void()) {
            return 0;
        }
        throw new IncomparableException('Invalid values to compare as ' . VoidType::class);
    }
}
