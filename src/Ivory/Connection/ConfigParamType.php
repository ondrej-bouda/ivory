<?php
namespace Ivory\Connection;

use Ivory\Exception\UnsupportedException;
use Ivory\Value\Quantity;

/**
 * Manages types of PostgreSQL configuration parameters.
 *
 * @see http://www.postgresql.org/docs/9.4/static/config-setting.html
 */
class ConfigParamType
{
    const BOOL = 1;
    const STRING = 2;
    const INTEGER = 3;
    const INTEGER_WITH_UNIT = 4;
    const REAL = 5;
    const ENUM = 6;

    public static function fromPostgreSQLName(string $varTypeName, bool $withUnit): int
    {
        switch (strtolower($varTypeName)) {
            case 'string':
                return self::STRING;
            case 'integer':
                return ($withUnit ? self::INTEGER_WITH_UNIT : self::INTEGER);
            case 'bool':
                return self::BOOL;
            case 'enum':
                return self::ENUM;
            case 'real':
                return self::REAL;
            default:
                throw new UnsupportedException('Unsupported type of configuration parameter');
        }
    }

    public static function detectType(string $typeName, $valueString, string $unit = null): int
    {
        $withUnit = ($unit || !is_numeric($valueString));
        return self::fromPostgreSQLName($typeName, $withUnit);
    }

    /**
     * @param int|string $type type to create the value as;
     *                         one of the {@link ConfigParamType} constants or the PostgreSQL vartype name
     * @param string|null $valueString the string representation of the value
     * @param string|null $unit unit for case a type with a unit is given;
     *                          <tt>null</tt> to take it from <tt>$valueString</tt> if relevant
     * @return bool|string|int|double|Quantity|null <tt>null</tt> iff <tt>$valueString</tt> is <tt>null</tt>
     */
    public static function createValue($type, $valueString, string $unit = null)
    {
        if ($valueString === null) {
            return null;
        }

        switch ($type) {
            default:
                return self::createValue(self::detectType($type, $valueString, $unit), $valueString, $unit);

            case self::STRING:
            case self::ENUM:
                return $valueString;

            case self::INTEGER:
                return (int)$valueString;

            case self::REAL:
                return (double)$valueString;

            case self::BOOL:
                switch (strtolower($valueString)) {
                    case 'on':
                    case 'true':
                    case 'tru':
                    case 'tr':
                    case 't':
                    case 'yes':
                    case 'ye':
                    case 'y':
                    case '1':
                        return true;

                    case 'off':
                    case 'of':
                    case 'false':
                    case 'fals':
                    case 'fal':
                    case 'fa':
                    case 'f':
                    case 'n':
                    case 'no':
                    case '0':
                        return false;

                    default:
                        throw new \UnexpectedValueException('Invalid boolean parameter value');
                }

            case self::INTEGER_WITH_UNIT:
                if ($unit !== null) {
                    return Quantity::fromValue($valueString, $unit);
                } else {
                    return Quantity::fromString($valueString);
                }
        }
    }
}
