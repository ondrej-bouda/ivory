<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;

/**
 * The UUID data type.
 *
 * Represented as the PHP `string` type.
 *
 * As defined by PostgreSQL, valid input is a sequence of 32 upper- or lowercase hexadecimal digits, where each group of
 * four digits may be separated by a hyphen from the rest of the string, and the whole string may be surrounded by
 * braces.
 *
 * @see https://www.postgresql.org/docs/11/datatype-uuid.html
 */
class UuidType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr): string
    {
        return $extRepr;
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!preg_match('~^({)?(?:[[:xdigit:]]{4}-?){7}[[:xdigit:]]{4}(?(1)})$~i', $val)) {
            throw $this->invalidValueException($val);
        }

        return $this->indicateType($strictType, Types::serializeString($val));
    }
}
