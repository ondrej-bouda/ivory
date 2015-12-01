<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;

/**
 * Representation of the PostgreSQL void type, i.e., nothing.
 *
 * There are just two possible values accepted or returned by this converter: `null` and {@link VoidType::void()}, which
 * is an empty singleton object.
 */
class VoidType extends BaseType
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
        }
        else {
            return self::void();
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        elseif ($val === self::void()) {
            return "''::{$this->getSchemaName()}.{$this->getName()}";
        }
        else {
            $this->throwInvalidValue($val);
        }
    }
}
