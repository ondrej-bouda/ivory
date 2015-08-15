<?php
namespace Ivory\Value;

/**
 * Representation of a MAC address.
 *
 * The objects are immutable.
 */
class MacAddr
{
    private $canonAddr;

    /**
     * Creates a MAC address from its string representation.
     *
     * The same input formats as by PostgreSQL are accepted. They are the following, where <tt>X</tt> stands for an
     * upper- or lowercase hexadecimal digit:
     * * <tt>'XX:XX:XX:XX:XX:XX'</tt>
     * * <tt>'XX-XX-XX-XX-XX-XX'</tt>
     * * <tt>'XXXXXX:XXXXXX'</tt>
     * * <tt>'XXXXXX-XXXXXX'</tt>
     * * <tt>'XXXX.XXXX.XXXX'</tt>
     * * <tt>'XXXXXXXXXXXX'</tt>
     *
     * @param string $addr the string representation of the MAC address in one of the formats mentioned above;
     *                     other formats are rejected by an <tt>\InvalidArgumentException</tt>
     * @return MacAddr
     */
    public static function fromString($addr)
    {
        $re = '~^
                [[:xdigit:]]{2}(?::[[:xdigit:]]{2}){5}
                |
                [[:xdigit:]]{2}(?:-[[:xdigit:]]{2}){5}
                |
                [[:xdigit:]]{6}[-:][[:xdigit:]]{6}
                |
                [[:xdigit:]]{4}\.[[:xdigit:]]{4}\.[[:xdigit:]]{4}
                |
                [[:xdigit:]]{12}
                $~x';
        if (!preg_match($re, $addr)) {
            throw new \InvalidArgumentException('$addr');
        }

        $canon = substr($addr, 0, 2);
        $pos = 2;
        for ($i = 0; $i < 5; $i++) {
            if (!ctype_xdigit($addr[$pos])) {
                $pos++;
            }
            $canon .= ':' . substr($addr, $pos, 2);
            $pos += 2;
        }

        return new MacAddr($canon);
    }

    private function __construct($canonAddr)
    {
        $this->canonAddr = $canonAddr;
    }

    /**
     * @return string the conventional canonical form of the MAC address, i.e., in the <tt>'XX:XX:XX:XX:XX:XX'</tt>
     *                  format, all the digits in lowercase
     */
    final public function toString()
    {
        return $this->canonAddr;
    }

    public function __toString()
    {
        return $this->toString();
    }
}
