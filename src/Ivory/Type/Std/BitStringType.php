<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\TypeBase;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\BitString;

/**
 * Base for bit string types.
 *
 * @see https://www.postgresql.org/docs/11/datatype-bit.html
 */
abstract class BitStringType extends TypeBase implements ITotallyOrderedType
{
    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        } elseif ($val instanceof BitString) {
            return $this->typeCastExpr($forceType, "B'" . $val->toString() . "'");
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
