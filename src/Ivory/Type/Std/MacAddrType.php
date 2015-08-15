<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\MacAddr;

/**
 * The MAC address data type.
 *
 * Represented as a {@link \Ivory\Value\MacAddr} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-net-types.html
 */
class MacAddrType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        try {
            return MacAddr::fromString($str);
        }
        catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof MacAddr) {
            $val = MacAddr::fromString($val);
        }

        return "'" . $val->toString() . "'";
    }
}
