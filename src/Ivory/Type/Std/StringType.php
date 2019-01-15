<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TypeBase;

/**
 * Character string.
 *
 * Represented as the PHP `string` type.
 *
 * @see https://www.postgresql.org/docs/11/datatype-character.html
 */
class StringType extends TypeBase implements ITotallyOrderedType
{
    public function parseValue(string $extRepr)
    {
        return $extRepr;
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        } else {
            return $this->indicateType($strictType, Types::serializeString((string)$val));
        }
    }
}
