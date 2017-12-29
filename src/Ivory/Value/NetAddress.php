<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\NotImplementedException;
use Ivory\Utils\IEqualable;

/**
 * Representation of an IPv4 or IPv6 host or network address.
 *
 * Both a host address and, optionally, its subnet may be represented in a single `NetAddress` object. Besides from
 * representing host addresses, `NetAddress` may also represent a network address.
 *
 * The objects are immutable.
 */
class NetAddress implements IEqualable
{
    private $addrStr;
    private $ipVersion;
    private $netmaskLength;

    /**
     * Creates a host or network address from its string representation, optionally accompanied by the netmask length.
     *
     * The CIDR (`address/y`) notation is automatically recognized in `$addr` and, if detected, the
     * `$netmaskLengthOrNetmask` argument gets ignored (a user warning is emitted if explicitly given anyway).
     *
     * @param string $addr the address, e.g., <tt>192.168.0.1</tt> or <tt>2001:4f8:3:ba::</tt>
     * @param int|string $netmaskLengthOrNetmask number of bits to take from <tt>$addr</tt> as the network prefix, or an
     *                                             IPv4 netmask, e.g., <tt>255.255.255.240</tt> being equivalent to 28
     * @return NetAddress
     */
    public static function fromString(string $addr, $netmaskLengthOrNetmask = null): NetAddress
    {
        if (strpos($addr, '/') !== false) {
            if ($netmaskLengthOrNetmask !== null) {
                trigger_error('Ignoring the netmask-related argument - a CIDR notation detected', E_USER_WARNING);
            }
            return self::fromCidrString($addr);
        }

        if (filter_var($addr, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('Invalid IP address');
        }

        return new NetAddress($addr, $netmaskLengthOrNetmask);
    }

    /**
     * Creates a host or network address from its CIDR notation.
     *
     * @param string $cidrAddr the address followed by a slash and the netmask length, e.g., <tt>192.168.0.3/24</tt>
     * @return NetAddress
     */
    public static function fromCidrString(string $cidrAddr): NetAddress
    {
        $sp = strrpos($cidrAddr, '/');
        if ($sp === false) {
            throw new \InvalidArgumentException('$cidrAddr');
        }

        $addr = substr($cidrAddr, 0, $sp);
        $netmaskLen = substr($cidrAddr, $sp + 1);
        if (strpos($addr, '/') !== false) {
            throw new \InvalidArgumentException('$cidrAddr');
        }

        return self::fromString($addr, $netmaskLen);
    }

    /**
     * Creates a host or network address from a binary string, also referred to as the "packed in_addr representation".
     *
     * E.g., the four-byte `"\x7f\x00\x00\x01"` binary string results in the address `127.0.0.1`.
     *
     * @param string $bytes binary string
     * @param int|string $netmaskLengthOrNetmask number of bits to take from <tt>$addr</tt> as the network prefix, or an
     *                                             IPv4 netmask, e.g., <tt>255.255.255.240</tt> being equivalent to 28
     * @return NetAddress
     */
    public static function fromByteString(string $bytes, $netmaskLengthOrNetmask = null): NetAddress
    {
        if (strlen($bytes) > 4 && !self::ipv6Support()) {
            throw new NotImplementedException('PHP must be compiled with IPv6 support');
        }

        $addrStr = @inet_ntop($bytes);
        if ($addrStr === false) {
            throw new \InvalidArgumentException('$bytes');
        }

        return new NetAddress($addrStr, $netmaskLengthOrNetmask);
    }

    /**
     * Creates an IPv4 address from its representation in an integer.
     *
     * @param int $ipv4Addr a proper IPv4 address representation; the same as what {@link long2ip()} accepts
     * @param int|string $netmaskLengthOrNetmask number of bits to take from <tt>$addr</tt> as the network prefix, or an
     *                                             IPv4 netmask, e.g., <tt>255.255.255.240</tt> being equivalent to 28
     * @return NetAddress
     */
    public static function fromInt(int $ipv4Addr, $netmaskLengthOrNetmask = null): NetAddress
    {
        $addrStr = @long2ip($ipv4Addr); // @: a warning would be issued when passed, e.g., an array
        if ($addrStr === null) {
            throw new \InvalidArgumentException('$ipv4Addr');
        }

        return new NetAddress($addrStr, $netmaskLengthOrNetmask);
    }

    /**
     * @return bool whether the PHP was built with IPv6 support enabled
     */
    private static function ipv6Support(): bool
    {
        static $cached = null;
        if ($cached === null) {
            ob_start();
            phpinfo(INFO_GENERAL);
            $info = ob_get_clean();
            $cached = (strpos($info, 'IPv6 Support => enabled') !== false);
        }
        return $cached;
    }


    private function __construct(string $addrStr, $netmaskLengthOrNetmask = null)
    {
        $this->addrStr = $addrStr;
        $this->ipVersion = (strpos($addrStr, ':') !== false ? 6 : 4);

        if ($netmaskLengthOrNetmask === null) {
            $this->netmaskLength = ($this->ipVersion == 6 ? 128 : 32);
        } else {
            if (filter_var($netmaskLengthOrNetmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                if ($this->ipVersion == 4) {
                    // taken from http://php.net/manual/en/function.ip2long.php#94787
                    $netmask = ip2long($netmaskLengthOrNetmask);
                    $base = ip2long('255.255.255.255');
                    $bits = log(($netmask ^ $base) + 1, 2);
                    if (abs($bits - (int)$bits) > 1e-9) {
                        throw new \InvalidArgumentException('Invalid netmask');
                    }
                    $this->netmaskLength = 32 - (int)$bits;
                } else {
                    throw new \InvalidArgumentException('Netmask may only be used for an IPv4 address.');
                }
            } else {
                $this->netmaskLength = filter_var($netmaskLengthOrNetmask, FILTER_VALIDATE_INT);
                if ($this->netmaskLength === false) {
                    throw new \InvalidArgumentException('$netmaskLengthOrNetmask');
                }
                $maxLen = ($this->ipVersion == 6 ? 128 : 32);
                if ($this->netmaskLength < 0 || $this->netmaskLength > $maxLen) {
                    throw new \OutOfBoundsException(
                        "Netmask length must be in range <0,$maxLen> for IPv{$this->ipVersion} addresses"
                    );
                }
            }
        }
    }


    /**
     * @return string the represented address
     */
    public function getAddressString(): string
    {
        return $this->addrStr;
    }

    /**
     * @return int number of bits used from the address as the network prefix
     */
    public function getNetmaskLength(): int
    {
        return $this->netmaskLength;
    }

    /**
     * @return int the IP version of this address
     */
    public function getIpVersion(): int
    {
        return $this->ipVersion;
    }

    /**
     * Expands the (IPv6) address to its full explicit form.
     *
     * There are several rules for abbreviating IPv6 addresses:
     * * leading zeros may be omitted in each group of four hexadecimal digits;
     * * one or more consecutive groups of 16 bits of zero may be left out;
     * * an IPv4-shaped address (dot-separated) may be used instead of the last two groups of hexadecimal digits.
     *
     * An address abbreviated using these rules gets expanded to the equivalent, fully explicit form, so the resulting
     * string is in the "x:x:x:x:x:x:x:x" format, where each "x" stands for four hexadecimal digits.
     *
     * Moreover, letters get converted to lowercase.
     *
     * @return string the IPv6 address expanded to its full explicit form; or the IPv4 address, as is
     */
    public function getExpandedAddress(): string
    {
        if ($this->ipVersion == 4) {
            return $this->addrStr;
        } else {
            if (!self::ipv6Support()) {
                throw new NotImplementedException('PHP must be compiled with IPv6 support');
            }
            $hexdigits = unpack('H*', inet_pton($this->addrStr))[1];
            $result = substr($hexdigits, 0, 4);
            for ($i = 1; $i < 8; $i++) {
                $result .= ':' . substr($hexdigits, $i * 4, 4);
            }
            return $result;
        }
    }

    /**
     * Returns the (IPv6) address in the canonical form, as described in RFC 5952.
     *
     * The rules for canonization are as follows:
     * * leading zeros must be suppressed (a single 16-bit zero field must be represented as 0, though);
     * * the "::" symbol must be used to shorten the address as much as possible, it must not be used to shorten just
     *   one 16-bit 0 field, and in case of a tie, it must shorten the first sequence of zero bits;
     * * the letters A-F must be represented in lowercase.
     *
     * There are some optional rules for embedded IPv4 addresses, using the dot decimal notation. These are not
     * implemented by this method, though. That is, the dot decimal notation is never used in the output of this method.
     *
     * @return string the IPv6 address canonized according to RFC 5952; or the IPv4 address, as is
     */
    public function getCanonicalAddress(): string
    {
        if ($this->ipVersion == 4) {
            return $this->addrStr;
        }

        $fields = explode(':', $this->getExpandedAddress());
        $fields[] = ''; // auxiliary sentinel

        // compute the longest zero sequence
        $lzsStart = -1;
        $lzsLen = 1; // so that only sequences of length at least 2 are considered
        $czsStart = null;
        $czsLen = 0;
        foreach ($fields as $i => $field) {
            if ($field === '0000') {
                if ($czsStart === null) {
                    $czsStart = $i;
                    $czsLen = 1;
                } else {
                    $czsLen++;
                }
            } else {
                if ($czsStart !== null) {
                    if ($czsLen > $lzsLen) {
                        $lzsStart = $czsStart;
                        $lzsLen = $czsLen;
                    }
                    $czsStart = null;
                }
            }
        }

        $result = '';
        if ($lzsStart == 0) {
            $result .= ':';
        }
        for ($i = 0; $i < 8; $i++) {
            if ($i > 0) {
                $result .= ':';
            }
            if ($i == $lzsStart) {
                $i += $lzsLen - 1;
                continue;
            }
            $result .= (ltrim($fields[$i], '0') ? : '0');
        }
        if ($lzsStart + $lzsLen == 8) {
            $result .= ':';
        }
        return $result;
    }

    /**
     * @return bool whether this address represents just a single host address, without any subnet specification
     */
    public function isSingleHost(): bool
    {
        if ($this->ipVersion == 6) {
            return ($this->netmaskLength == 128);
        } else {
            return ($this->netmaskLength == 32);
        }
    }

    /**
     * @return bool whether this address represents a network, which is iff all the host number bits are zero;
     *              e.g., <tt>127.0.49.0/24</tt> represents a network, while <tt>127.0.49.0/23</tt> does not
     */
    public function isNetwork(): bool
    {
        if ($this->ipVersion == 4) {
            $hostPartLen = 32 - $this->netmaskLength;
            $mask = (1 << $hostPartLen) - 1;
            return ((ip2long($this->addrStr) & $mask) == 0);
        } else {
            $hostPartLen = 128 - $this->netmaskLength;
            $hostPartOctets = (int)floor($hostPartLen / 8);
            $hostPartLeadBits = $hostPartLen % 8;
            $bs = $this->toByteString();
            for ($i = 15; $i >= 16 - $hostPartOctets; $i--) {
                if (ord($bs[$i]) != 0) {
                    return false;
                }
            }
            if ($hostPartLeadBits > 0) {
                $leadByte = ord($bs[$i]);
                $mask = (1 << $hostPartLeadBits) - 1;
                if (($leadByte & $mask) != 0) {
                    return false;
                }
            }
            return true;
        }
    }

    /**
     * Finds out whether this address is equal to the given one.
     *
     * Two addresses are considered as equal iff they are of the same IP version, the same netmask, and the same
     * expanded address.
     *
     * @param NetAddress|string $address a {@link NetAddress} or anything {@link NetAddress::fromString()} accepts as
     *                                     its first argument
     * @return bool|null
     */
    public function equals($address): ?bool
    {
        if ($address === null) {
            return null;
        }
        if (!$address instanceof NetAddress) {
            $address = NetAddress::fromString($address);
        }

        return (
            $this->ipVersion == $address->ipVersion &&
            $this->netmaskLength == $address->netmaskLength &&
            $this->getExpandedAddress() == $address->getExpandedAddress()
        );
    }

    /**
     * Finds out whether this network contains a host address or a whole given network as a subnetwork.
     *
     * Note that, in conformance with the standard PostgreSQL `>>` operator, equal networks are not considered as
     * containing each other, i.e., this method is a test for strict containment. To also permit equality, use
     * {@link NetAddress::containsOrEquals()}.
     *
     * For addresses of different IP version, `false` is always returned.
     *
     * @param NetAddress|string $address a {@link NetAddress} or anything {@link NetAddress::fromString()} accepts as
     *                                     its first argument
     * @return bool <tt>true</tt> if <tt>$address</tt> is strictly contained in this address, <tt>false</tt> otherwise
     *              (especially in case both networks are the same, or if this is actually a single host, not a network)
     */
    public function contains($address): bool
    {
        if (!$address instanceof NetAddress) {
            $address = NetAddress::fromString($address);
        }

        if ($this->ipVersion != $address->ipVersion) {
            return false;
        }
        if ($this->netmaskLength >= $address->netmaskLength) {
            return false;
        }

        // now, the first $this->netmaskLength bits must match, and that's it
        $octets = (int)floor($this->netmaskLength / 8);
        $remBits = $this->netmaskLength % 8;

        $thisBS = $this->toByteString();
        $addrBS = $address->toByteString();
        if (strncmp($thisBS, $addrBS, $octets) != 0) {
            return false;
        }

        if ($remBits > 0) {
            $thisByte = ord($thisBS[$octets]);
            $addrByte = ord($addrBS[$octets]);
            $mask = ord("\xff") << (8 - $remBits);
            if ((($thisByte ^ $addrByte) & $mask) != 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param NetAddress|string $address
     * @return bool
     */
    public function containsOrEquals($address): bool
    {
        return ($this->equals($address) || $this->contains($address));
    }

    /**
     * Returns the (human-readable) string representation of the address.
     *
     * If only a single host is represented by this address, just the host address. On the contrary, if a network is
     * represented by this address, the CIDR representation is used.
     *
     * @return string the (human-readable) string representation of the address
     */
    public function toString(): string
    {
        if ($this->isSingleHost()) {
            return $this->addrStr;
        } else {
            return $this->toCidrString();
        }
    }

    /**
     * @return string the CIDR representation of this address, as defined in RFC 4632, e.g., <tt>"123.8.9.1/26"</tt>
     */
    public function toCidrString(): string
    {
        return $this->addrStr . '/' . $this->netmaskLength;
    }

    /**
     * Packs the address to a binary string, also referred to as the "packed in_addr representation".
     *
     * E.g., the address `127.0.0.1` results in the four-byte `"\x7f\x00\x00\x01"` binary string.
     *
     * @return string the address packed to a binary string
     */
    public function toByteString(): string
    {
        if ($this->ipVersion == 4 || self::ipv6Support()) {
            return inet_pton($this->addrStr);
        } else {
            throw new NotImplementedException('PHP must be compiled with IPv6 support');
        }
    }

    /**
     * Packs the IPv4 address to an integer.
     *
     * Note that results may vary depending on the platform: on a 32-bit PHP, the highest bit set yields a negative
     * integer, whereas on a 64-bit PHP, any value returned by this method is a non-negative integer.
     *
     * @return int the address represented as an integer
     * @throws \LogicException when called on an IPv6 address, as such an address does not fit into the integer type
     */
    public function toInt(): int
    {
        if ($this->ipVersion == 4) {
            return ip2long($this->addrStr);
        } else {
            throw new \LogicException('IPv6 address cannot be converted to long');
        }
    }

    public function __toString()
    {
        return $this->toString();
    }
}
