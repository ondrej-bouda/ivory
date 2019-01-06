<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\NetAddress;

/**
 * The data type for holding IPv4 and IPv6 hosts and networks.
 *
 * Represented as a {@link \Ivory\Value\NetAddress} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-net-types.html#DATATYPE-INET
 * @todo #21 implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class InetType extends BaseType
{
    public function parseValue(string $extRepr)
    {
        try {
            return NetAddress::fromString($extRepr);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof NetAddress) {
            $val = NetAddress::fromString($val);
        }

        return "'" . $val->toString() . "'";
    }
}
