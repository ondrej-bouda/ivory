<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\Decimal;
use Ivory\Value\Money;

/**
 * The money data type.
 *
 * Represented as a {@link \Ivory\Value\Money} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-money.html
 * @see http://www.postgresql.org/message-id/flat/20130328092819.237c0106@imp#20130328092819.237c0106@imp discussion on issues of the money type
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class MoneyType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $decPoint = $this->getConnection()->getConfig()->getMoneyDecimalSeparator();
        try {
            return Money::fromString($str, $decPoint);
        }
        catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if ($val instanceof Money) {
            $str = $val->getAmount()->toString();
        }
        elseif ($val instanceof Decimal) {
            $str = $val->toString();
        }
        elseif (is_int($val) || is_float($val) || filter_var($val, FILTER_VALIDATE_FLOAT)) {
            $str = (string)$val;
        }
        elseif (is_string($val)) {
            $str = Money::fromString($val, '.')->getAmount()->toString();
        }
        else {
            $this->throwInvalidValue($val);
        }

        return $str . '::money';
    }
}
