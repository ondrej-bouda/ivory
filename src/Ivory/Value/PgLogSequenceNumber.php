<?php
namespace Ivory\Value;

use Ivory\Utils\EqualableWithPhpOperators;
use Ivory\Utils\IEqualable;

/**
 * Representation of a PostgreSQL log sequence number.
 *
 * Besides being {@link IEqualable}, the {@link PgLogSequenceNumber} objects may safely be compared using the `<`,
 * `==`, and `>` operators with the expected results.
 *
 * The objects are immutable.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-pg-lsn.html
 */
class PgLogSequenceNumber implements IEqualable
{
    use EqualableWithPhpOperators;

    /** @var int the higher 32 bits */
    private $hi;
    /** @var int the lower 32 bits */
    private $lo;

    /**
     * @param string $str the string representation of a log sequence number, in the %X/%X format
     * @return PgLogSequenceNumber
     * @throws \InvalidArgumentException when the string does not satisfy the accepted format
     */
    public static function fromString(string $str): PgLogSequenceNumber
    {
        $scanned = sscanf($str, '%X/%X', $hi, $lo);
        if ($scanned == 2) {
            return new PgLogSequenceNumber($hi, $lo);
        } else {
            throw new \InvalidArgumentException('$str');
        }
    }

    private function __construct($hi, $lo)
    {
        $this->hi = $hi;
        $this->lo = $lo;
    }


    public function toString(): string
    {
        return sprintf('%X/%X', $this->hi, $this->lo);
    }
}
