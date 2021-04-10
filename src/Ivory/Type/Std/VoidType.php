<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;

/**
 * Representation of the PostgreSQL void type, i.e., nothing.
 *
 * There are just two possible values accepted or returned by this type object: `null` and {@link VoidType::void()},
 * which is an empty singleton object.
 */
class VoidType extends TypeBase
{
    /** @noinspection PhpMissingReturnTypeInspection no need to expose as the interface constract */
    public static function void()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new \stdClass();
        }
        return $inst;
    }

    /** @noinspection PhpMissingReturnTypeInspection no need to expose as the interface constract */
    public function parseValue(string $extRepr)
    {
        return self::void();
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } elseif ($val === self::void()) {
            return $this->indicateType($strictType, "''");
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
