<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\NetAddress;

/**
 * The data type for holding IPv4 and IPv6 networks.
 *
 * Represented as a {@link \Ivory\Value\NetAddress} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-net-types.html
 */
class CidrType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        try {
            return NetAddress::fromCidrString($str);
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

        if (!$val instanceof NetAddress) {
            $val = NetAddress::fromString($val);
        }

        return "'" . $val->toCidrString() . "'";
    }
}
