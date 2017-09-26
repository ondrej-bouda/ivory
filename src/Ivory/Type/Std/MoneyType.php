<?php
declare(strict_types=1);

namespace Ivory\Type\Std;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\Config\ConnConfigValueRetriever;
use Ivory\Connection\IConnection;
use Ivory\Exception\IncomparableException;
use Ivory\Type\ConnectionDependentBaseType;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Decimal;
use Ivory\Value\Money;

/**
 * The money data type.
 *
 * Represented as a {@link \Ivory\Value\Money} object.
 *
 * Note the PostgreSQL `money` type has plenty of issues, the most serious one being that the output is locale-sensitive
 * and thus restoring a database might import wrong values if the target database has a different setting of
 * `lc_monetary`. Besides, the only correct way of parsing the values is getting the decimal separator used in the
 * session and only then interpreting the value string.
 *
 * For a more thorough discussion of problems of the money type, see
 * {@link http://www.postgresql.org/message-id/flat/20130328092819.237c0106@imp#20130328092819.237c0106@imp}.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-money.html
 */
class MoneyType extends ConnectionDependentBaseType implements ITotallyOrderedType
{
    /** @var ConnConfigValueRetriever */
    private $decSepRetriever;

    public function attachToConnection(IConnection $connection): void
    {
        $this->decSepRetriever = new ConnConfigValueRetriever($connection->getConfig(), ConfigParam::MONEY_DEC_SEP);
    }

    public function detachFromConnection(): void
    {
        $this->decSepRetriever = null;
    }

    public function parseValue(string $str)
    {
        $decSep = $this->decSepRetriever->getValue();
        try {
            return Money::fromString($str, $decSep);
        } catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
        }
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if ($val instanceof Money) {
            $str = $val->getAmount()->toString();
        } elseif ($val instanceof Decimal) {
            $str = $val->toString();
        } elseif (is_int($val) || is_float($val) || filter_var($val, FILTER_VALIDATE_FLOAT)) {
            $str = (string)$val;
        } elseif (is_string($val)) {
            $str = Money::fromString($val, '.')->getAmount()->toString();
        } else {
            $this->throwInvalidValue($val);
        }

        return $str . '::money';
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        if (!$a instanceof Money) {
            throw new IncomparableException('$a is not of class ' . Money::class);
        }
        if (!$b instanceof Money) {
            throw new IncomparableException('$b is not of class ' . Money::class);
        }
        if (!$a->getUnit() != $b->getUnit()) {
            throw new IncomparableException('$a and $b of not the same monetary unit');
        }

        return $a->getAmount()->compareTo($b->getAmount());
    }
}
