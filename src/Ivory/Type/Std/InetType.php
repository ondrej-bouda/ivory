<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Value\NetAddress;

/**
 * The data type for holding IPv4 and IPv6 hosts and networks.
 *
 * Represented as a {@link \Ivory\Value\NetAddress} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-net-types.html#DATATYPE-INET
 * @todo #21 implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class InetType extends TypeBase
{
    public function parseValue(string $extRepr)
    {
        try {
            return NetAddress::fromString($extRepr);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof NetAddress) {
            $val = NetAddress::fromString($val);
        }

        return $this->indicateType($forceType, "'" . $val->toString() . "'");
    }
}
