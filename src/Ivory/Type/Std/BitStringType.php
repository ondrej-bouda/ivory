<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\BitString;

/**
 * Base for bit string types.
 *
 * @see https://www.postgresql.org/docs/11/datatype-bit.html
 */
abstract class BitStringType extends BaseType implements ITotallyOrderedType
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } elseif ($val instanceof BitString) {
            return "B'" . $val->toString() . "'";
        } else {
            throw $this->invalidValueException($val);
        }
    }
}
