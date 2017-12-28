<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Exception\IncomparableException;
use Ivory\Type\BaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\BitString;

/**
 * Base for bit string types.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-bit.html
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

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        if (!$a instanceof BitString) {
            throw new IncomparableException('$a is not a ' . BitString::class);
        }
        if (!$b instanceof BitString) {
            throw new IncomparableException('$b is not a ' . BitString::class);
        }
        return strcmp($a->toString(), $b->toString());
    }
}
