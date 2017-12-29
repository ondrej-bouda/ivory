<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\MacAddr;

/**
 * The MAC address data type.
 *
 * Represented as a {@link \Ivory\Value\MacAddr} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-net-types.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class MacAddrType extends BaseType
{
    public function parseValue(string $str)
    {
        try {
            return MacAddr::fromString($str);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($str, $e);
        }
    }

    public function serializeValue($val): string
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
