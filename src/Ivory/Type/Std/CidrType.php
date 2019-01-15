<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Value\NetAddress;

/**
 * The data type for holding IPv4 and IPv6 networks.
 *
 * Represented as a {@link \Ivory\Value\NetAddress} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-net-types.html#DATATYPE-CIDR
 * @todo #21 implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class CidrType extends TypeBase
{
    public function parseValue(string $extRepr)
    {
        try {
            return NetAddress::fromCidrString($extRepr);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof NetAddress) {
            $val = NetAddress::fromString($val);
        }

        return $this->indicateType($strictType, "'" . $val->toCidrString() . "'");
    }
}
