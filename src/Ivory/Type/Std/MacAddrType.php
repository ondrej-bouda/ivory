<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Value\MacAddr;

/**
 * The 6-byte MAC address data type.
 *
 * Represented as a {@link \Ivory\Value\MacAddr} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-net-types.html#DATATYPE-MACADDR
 * @todo #21 implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class MacAddrType extends TypeBase
{
    public function parseValue(string $extRepr)
    {
        try {
            return MacAddr::fromString($extRepr);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof MacAddr) {
            $val = MacAddr::fromString($val);
        }

        return $this->indicateType($strictType, "'" . $val->toString() . "'");
    }
}
