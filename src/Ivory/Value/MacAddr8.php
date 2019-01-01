<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ParseException;

/**
 * Representation of an 8-byte MAC address.
 *
 * The objects are immutable.
 */
class MacAddr8
{
    private $canonAddr;

    /**
     * Creates an 8-byte MAC address from its string representation.
     *
     * The same input formats as by PostgreSQL are accepted. Consistently with PostgreSQL, both 6 and 8 byte MAC
     * addresses are accepted, 6 bytes converted to 8 bytes by 4th and 5th bytes set to FF and FE, respectively.
     *
     * Any input which is comprised of pairs of hex digits (on byte boundaries), optionally separated consistently by
     * one of `':'`, `'-'` or `'.'`, is accepted. The number of hex digits must be either 16 (8 bytes) or 12 (6 bytes).
     * Leading and trailing whitespace is ignored. The following are examples of input formats that are accepted:
     * `'XX:XX:XX:XX:XX:XX:XX:XX'`
     * `'XX-XX-XX-XX-XX-XX-XX-XX'`
     * `'XXXXXX:XXXXXXXXXX'`
     * `'XXXXXX-XXXXXXXXXX'`
     * `'XXXX.XXXX.XXXX.XXXX'`
     * `'XXXX-XXXX-XXXX-XXXX'`
     * `'XXXXXXXX:XXXXXXXX'`
     * `'XXXXXXXXXXXXXXXX'`
     *
     * @param string $addr the string representation of the 8-byte MAC address in one of the formats mentioned above;
     *                     other formats are rejected by a {@link ParseException}
     * @return MacAddr8
     */
    public static function fromString(string $addr): MacAddr8
    {
        $canon = '';
        $len = strlen($addr);
        $sep = null;
        $digits = 0;
        for ($i = 0; $i < $len; $i++) {
            $c = $addr[$i];
            $digitPair = ($digits % 2 == 0);
            if (ctype_xdigit($c)) {
                if ($digitPair && $digits > 0) {
                    $canon .= ':';
                }
                $canon .= $c;
                $digits++;
            } elseif ($sep === null && ($c == ':' || $c == '-' || $c == '.') && $digitPair) {
                $sep = $c;
            } elseif (!($c === $sep && $digitPair) && !ctype_space($c)) {
                throw new ParseException('Invalid macaddr8 string', $i);
            }
        }

        if ($digits == 12) {
            $canon = substr($canon, 0, 8) . ':ff:fe' . substr($canon, 8);
        } elseif ($digits != 16) {
            throw new ParseException('Invalid macaddr8 string: only 6 or 8 bytes may be provided');
        }

        return new MacAddr8(strtolower($canon));
    }

    /**
     * Creates an 8-byte MAC address from a 6-byte MAC address using the standard conversion.
     *
     * 8 bytes are made from 6 bytes in such a way that bytes `FFFE` are inserted in the middle of the 6 bytes.
     *
     * @see from6ByteMacAddrForIp6() for a modified conversion used by IPv6
     * @param MacAddr $macAddr
     * @return MacAddr8
     */
    public static function from6ByteMacAddr(MacAddr $macAddr): MacAddr8
    {
        $inStr = $macAddr->toString();
        $canon = substr($inStr, 0, 8) . ':ff:fe' . substr($inStr, 8);
        return new MacAddr8($canon);
    }

    /**
     * Creates an 8-byte MAC address from a 6-byte MAC address using the IPv6 conversion.
     *
     * 8 bytes are made from 6 bytes in such a way that `FFFE` are inserted in the middle of the 6 bytes, and the 7th
     * bit is set. This is the conversion used by IPv6.
     *
     * @see from6ByteMacAddr() for the standard conversion, i.e., without setting the 7th bit
     * @param MacAddr $macAddr
     * @return MacAddr8
     */
    public static function from6ByteMacAddrForIp6(MacAddr $macAddr): MacAddr8
    {
        $inStr = $macAddr->toString();
        $scanned = sscanf(substr($inStr, 0, 2), '%x', $firstByte);
        assert($scanned == 1);
        $firstByte |= 0b000000010;
        $canon = sprintf('%02x', $firstByte) . substr($inStr, 2, 6) . ':ff:fe' . substr($inStr, 8);

        return new MacAddr8($canon);
    }

    private function __construct(string $canonAddr)
    {
        $this->canonAddr = $canonAddr;
    }

    /**
     * @return string the conventional canonical form of the 8-byte MAC address, i.e., in the
     *                  <tt>'XX:XX:XX:XX:XX:XX:XX:XX'</tt> format, all the digits in lowercase
     */
    final public function toString(): string
    {
        return $this->canonAddr;
    }

    public function __toString()
    {
        return $this->toString();
    }
}
