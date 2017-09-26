<?php
declare(strict_types=1);

namespace Ivory\Value;

use Ivory\Utils\System;

class NetAddressTest extends \PHPUnit\Framework\TestCase
{
    public function testFromString()
    {
        $a = NetAddress::fromString('1.2.3.4/5');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(5, $a->getNetmaskLength());

        $a = NetAddress::fromString('1.2.3.4');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(32, $a->getNetmaskLength());

        $a = NetAddress::fromString('1.2.3.4', 24);
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(24, $a->getNetmaskLength());

        $a = NetAddress::fromString('1.2.3.4', '255.255.255.0');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(24, $a->getNetmaskLength());

        $a = NetAddress::fromString('1.2.3.4', '255.255.255.192');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(26, $a->getNetmaskLength());

        $a = NetAddress::fromString('1.2.3.4/0');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(0, $a->getNetmaskLength());

        try {
            NetAddress::fromString('1.2.3.4/24', 16);
            $this->fail(\PHPUnit\Framework\Error\Warning::class . ' expected');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
        }

        try {
            NetAddress::fromString('7');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromString('1.2.3.4/8/16');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromString('1.2.3.4/33');
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromString('1.2.3.4', -1);
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromString('01.2.3.4');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        $a = NetAddress::fromString('2001:db8::1', 5);
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::1', $a->getAddressString());
        $this->assertSame(5, $a->getNetmaskLength());

        $a = NetAddress::fromString('2001:db8::1');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::1', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromString('2001:db8::1/80');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::1', $a->getAddressString());
        $this->assertSame(80, $a->getNetmaskLength());

        $a = NetAddress::fromString('2001:db8::127.0.0.1');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::127.0.0.1', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromString('ABCD:234::');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('ABCD:234::', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromString('::');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('::', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        try {
            NetAddress::fromString('ABCD::234::');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromString('a::', '255.255.255.255');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromString('ABCD:234::/129');
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromString(':::');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromCidrString()
    {
        $a = NetAddress::fromCidrString('1.2.3.4/5');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(5, $a->getNetmaskLength());

        try {
            NetAddress::fromCidrString('1.2.3.4');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromCidrString('1.2.3.4/33');
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromCidrString('7');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromCidrString('1.2.3.4/8/16');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromCidrString('01.2.3.4/24');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        $a = NetAddress::fromCidrString('2001:db8::1/80');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::1', $a->getAddressString());
        $this->assertSame(80, $a->getNetmaskLength());

        try {
            NetAddress::fromCidrString('2001:db8::1');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        $a = NetAddress::fromCidrString('2001:db8::127.0.0.1/120');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('2001:db8::127.0.0.1', $a->getAddressString());
        $this->assertSame(120, $a->getNetmaskLength());

        $a = NetAddress::fromCidrString('ABCD:234::/128');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('ABCD:234::', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromCidrString('::/127');
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('::', $a->getAddressString());
        $this->assertSame(127, $a->getNetmaskLength());

        try {
            NetAddress::fromCidrString('ABCD::234::/123');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromCidrString('ABCD:234::/129');
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromCidrString(':::/123');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromByteString()
    {
        $a = NetAddress::fromByteString(chr(127) . chr(0) . chr(0) . chr(1));
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('127.0.0.1', $a->getAddressString());
        $this->assertSame(32, $a->getNetmaskLength());

        $a = NetAddress::fromByteString(chr(0) . chr(0) . chr(0) . chr(0), 14);
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('0.0.0.0', $a->getAddressString());
        $this->assertSame(14, $a->getNetmaskLength());

        $a = NetAddress::fromByteString(chr(0) . chr(255) . chr(255) . chr(255), '255.224.0.0');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('0.255.255.255', $a->getAddressString());
        $this->assertSame(11, $a->getNetmaskLength());

        try {
            NetAddress::fromByteString(chr(0) . chr(255) . chr(255) . chr(255), '255.112.0.0');
            $this->fail('\InvalidArgumentException expected due to an incorrect netmask');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString('');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString('127');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString(chr(14));
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString(chr(1) . chr(2) . chr(3) . chr(4), 33);
            $this->fail('\OutOfBoundsException expected');
        } catch (\OutOfBoundsException $e) {
        }

        try {
            NetAddress::fromByteString(chr(1) . chr(2) . chr(3) . chr(4) . chr(5));
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString(
                chr(1) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6) . chr(7) . chr(8) .
                chr(9) . chr(10) . chr(11) . chr(12) . chr(13) . chr(14) . chr(15)
            );
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        $a = NetAddress::fromByteString(
            chr(1) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6) . chr(7) . chr(8) .
            chr(9) . chr(10) . chr(11) . chr(12) . chr(13) . chr(14) . chr(15) . chr(16)
        );
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('102:304:506:708:90a:b0c:d0e:f10', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromByteString(
            chr(1) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6) . chr(7) . chr(8) .
            chr(9) . chr(10) . chr(11) . chr(12) . chr(13) . chr(14) . chr(15) . chr(16),
            35
        );
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('102:304:506:708:90a:b0c:d0e:f10', $a->getAddressString());
        $this->assertSame(35, $a->getNetmaskLength());

        $a = NetAddress::fromByteString(
            chr(254) . str_repeat(chr(0), 10) . chr(123) . chr(4) . chr(0) . chr(0) . chr(0)
        );
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('fe00::7b:400:0', $a->getAddressString());
        $this->assertSame(128, $a->getNetmaskLength());

        $a = NetAddress::fromByteString(str_repeat(chr(0), 16), 0);
        $this->assertSame(6, $a->getIpVersion());
        $this->assertSame('::', $a->getAddressString());
        $this->assertSame(0, $a->getNetmaskLength());

        try {
            NetAddress::fromByteString(str_repeat(chr(0), 16), '255.255.255.255');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            NetAddress::fromByteString(
                chr(1) . chr(2) . chr(3) . chr(4) . chr(5) . chr(6) . chr(7) . chr(8) .
                chr(9) . chr(10) . chr(11) . chr(12) . chr(13) . chr(14) . chr(15) . chr(16) .
                chr(17)
            );
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromInt()
    {
        $a = NetAddress::fromInt(16909060);
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(32, $a->getNetmaskLength());

        $a = NetAddress::fromInt(16909060, 16);
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(16, $a->getNetmaskLength());

        $a = NetAddress::fromInt(16909060, '255.255.255.0');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(24, $a->getNetmaskLength());

        $a = NetAddress::fromInt(16909060, '255.255.255.192');
        $this->assertSame(4, $a->getIpVersion());
        $this->assertSame('1.2.3.4', $a->getAddressString());
        $this->assertSame(26, $a->getNetmaskLength());

        if (System::is32Bit()) {
            $a = NetAddress::fromInt(-1073732954);
            $this->assertSame(4, $a->getIpVersion());
            $this->assertSame('192.0.34.166', $a->getAddressString());
            $this->assertSame(32, $a->getNetmaskLength());

            $a = NetAddress::fromInt(-1);
            $this->assertSame(4, $a->getIpVersion());
            $this->assertSame('255.255.255.255', $a->getAddressString());
            $this->assertSame(32, $a->getNetmaskLength());
        } else {
            $a = NetAddress::fromInt(3221234342);
            $this->assertSame(4, $a->getIpVersion());
            $this->assertSame('192.0.34.166', $a->getAddressString());
            $this->assertSame(32, $a->getNetmaskLength());

            $a = NetAddress::fromInt(4294967295);
            $this->assertSame(4, $a->getIpVersion());
            $this->assertSame('255.255.255.255', $a->getAddressString());
            $this->assertSame(32, $a->getNetmaskLength());
        }
    }

    public function testGetExpandedAddress()
    {
        $this->assertSame('172.16.254.1',
            NetAddress::fromString('172.16.254.1')->getExpandedAddress()
        );
        $this->assertSame('2001:0db8:0000:0000:0000:0008:0800:417a',
            NetAddress::fromString('2001:DB8::8:800:417A')->getExpandedAddress()
        );
        $this->assertSame('2001:0db8:0000:0000:0000:0000:0000:0000',
            NetAddress::fromString('2001:db8::')->getExpandedAddress()
        );
        $this->assertSame('0000:0000:0000:0000:0000:2001:0db8:0000',
            NetAddress::fromString('::2001:db8:0')->getExpandedAddress()
        );
        $this->assertSame('0000:0000:0000:0000:0000:0000:0000:0001',
            NetAddress::fromString('::1')->getExpandedAddress()
        );
        $this->assertSame('0000:0000:0000:0000:0000:0000:0000:0000',
            NetAddress::fromString('::')->getExpandedAddress()
        );
        $this->assertSame('abcd:ef01:2345:6789:abcd:ef01:2345:6789',
            NetAddress::fromString('ABCD:EF01:2345:6789:ABCD:EF01:2345:6789')->getExpandedAddress()
        );
        $this->assertSame('abcd:0234:0000:0000:0000:0001:7f00:0001',
            NetAddress::fromString('ABCD:234::1:127.0.0.1')->getExpandedAddress()
        );
    }

    public function testGetCanonicalAddress()
    {
        $this->assertSame('45.97.123.0', NetAddress::fromString('45.97.123.0')->getCanonicalAddress());

        $this->assertSame('2001:db8::1', NetAddress::fromString('2001:0Db8::0001')->getCanonicalAddress());
        $this->assertSame('2001:db8::2:1', NetAddress::fromString('2001:db8:0:0:0:0:2:1')->getCanonicalAddress());
        $this->assertSame('2001:db8::', NetAddress::fromString('2001:0db8::0')->getCanonicalAddress());
        $this->assertSame('::1', NetAddress::fromString('0:0:0:0:0:0:0:1')->getCanonicalAddress());
        $this->assertSame('::', NetAddress::fromString('0:0:0:0:0:0:0:0')->getCanonicalAddress());
        $this->assertSame('20a:db8:0:1:1:1:1:1', NetAddress::fromString('20a:db8::1:1:1:1:1')->getCanonicalAddress());
        $this->assertSame('2001:0:0:1::1', NetAddress::fromString('2001:0:0:1:0:0:0:1')->getCanonicalAddress());
        $this->assertSame('2001:db8::1:0:0:1', NetAddress::fromString('2001:db8:0:0:1:0:0:1')->getCanonicalAddress());
        $this->assertSame('64:ff9b::c000:221', NetAddress::fromString('64:ff9b::192.0.2.33')->getCanonicalAddress());
        $this->assertSame('abcd:234::1:7f00:1', NetAddress::fromString('ABCD:234::1:127.0.0.1')->getCanonicalAddress());
        $this->assertSame('abc:23:f:f:0:1::', NetAddress::fromString('0ABC:23:F:F::1:0.0.0.0')->getCanonicalAddress());
    }

    public function testIsSingleHost()
    {
        $this->assertTrue(NetAddress::fromCidrString('127.0.49.0/32')->isSingleHost());
        $this->assertFalse(NetAddress::fromCidrString('127.0.49.0/31')->isSingleHost());
        $this->assertTrue(NetAddress::fromCidrString('127.0.49.255/32')->isSingleHost());

        $this->assertTrue(NetAddress::fromCidrString('ff::af00/128')->isSingleHost());
        $this->assertFalse(NetAddress::fromCidrString('ff::af00/127')->isSingleHost());
        $this->assertTrue(NetAddress::fromCidrString('a::127.0.0.1/128')->isSingleHost());
        $this->assertFalse(NetAddress::fromCidrString('a::127.0.0.1/127')->isSingleHost());
    }

    public function testIsNetwork()
    {
        $this->assertTrue(NetAddress::fromCidrString('127.0.49.0/32')->isNetwork());
        $this->assertTrue(NetAddress::fromCidrString('127.0.49.0/24')->isNetwork());
        $this->assertFalse(NetAddress::fromCidrString('127.0.49.0/23')->isNetwork());

        $this->assertTrue(NetAddress::fromCidrString('ff::af00/120')->isNetwork());
        $this->assertFalse(NetAddress::fromCidrString('ff::af00/119')->isNetwork());
        $this->assertTrue(NetAddress::fromCidrString('0.0.0.0/0')->isNetwork());
        $this->assertTrue(NetAddress::fromCidrString('a::127.0.0.0/104')->isNetwork());
        $this->assertFalse(NetAddress::fromCidrString('a::127.0.0.1/104')->isNetwork());
        $this->assertFalse(NetAddress::fromCidrString('a::127.0.0.0/103')->isNetwork());
    }

    public function testEquals()
    {
        $this->assertTrue(NetAddress::fromString('1.2.3.4')->equals(NetAddress::fromString('1.2.3.4')));
        $this->assertTrue(NetAddress::fromString('1.2.3.4/32')->equals('1.2.3.4'));
        $this->assertFalse(NetAddress::fromString('1.2.3.4')->equals('1.2.3.5'));
        $this->assertTrue(NetAddress::fromString('1.2.3.4/24')->equals('1.2.3.4/24'));
        $this->assertFalse(NetAddress::fromString('1.2.3.4/24')->equals('1.2.3.4/25'));
        $this->assertFalse(NetAddress::fromString('1.2.3.4/25')->equals('1.2.3.4/24'));

        $this->assertFalse(NetAddress::fromString('1.2.3.4')->equals('::1.2.3.4'));

        $this->assertTrue(NetAddress::fromString('0ff::102:304')->equals('ff:0::1.2.3.4'));
        $this->assertFalse(NetAddress::fromString('0ff::102:304')->equals('ff:0::1.2.3.5'));
        $this->assertTrue(NetAddress::fromString('0ff::102:304/110')->equals('ff:0::1.2.3.4/110'));
        $this->assertFalse(NetAddress::fromString('0ff::102:304/64')->equals('ff:0::1.2.3.4/110'));
    }

    public function testContains()
    {
        $this->assertTrue(NetAddress::fromString('192.168.1.0/25')->contains(NetAddress::fromString('192.168.1.3')));
        $this->assertTrue(NetAddress::fromString('192.168.1.0/31')->contains('192.168.1.0'));
        $this->assertFalse(NetAddress::fromString('192.168.1.0/31')->contains('192.168.1.3'));
        $this->assertFalse(NetAddress::fromString('192.168.1.0')->contains('192.168.1.0'));
        $this->assertTrue(NetAddress::fromString('192.168.1.0/25')->contains('192.168.1.127'));
        $this->assertFalse(NetAddress::fromString('192.168.1.0/25')->contains('192.168.1.128'));

        $this->assertTrue(NetAddress::fromString('192.168.1.0/24')->contains('192.168.1.0/25'));
        $this->assertFalse(NetAddress::fromString('192.168.1.0/24')->contains('192.168.1.0/24'));
        $this->assertFalse(NetAddress::fromString('192.168.1.0/25')->contains('192.168.1.0/24'));
        $this->assertTrue(NetAddress::fromString('192.168.1.128/24')->contains('192.168.1.0/25'));

        $this->assertTrue(NetAddress::fromString('f7ab:1::5bcd/64')->contains('f7ab:0001:0::1:2:3:4'));
        $this->assertTrue(NetAddress::fromString('f7ab:1::5bcd/79')->contains('f7ab:0001:0::1:2:3:4'));
        $this->assertFalse(NetAddress::fromString('f7ab:1::5bcd/80')->contains('f7ab:0001:0::1:2:3:4'));
        $this->assertTrue(NetAddress::fromString('::/0')->contains('1234::'));
        $this->assertTrue(NetAddress::fromString('f7ab::ffff:127.0.0.0/125')->contains('f7ab:0::ffff:7f00:0007'));
        $this->assertFalse(NetAddress::fromString('f7ab::ffff:127.0.0.0/125')->contains('f7ab:0::ffff:7f00:0008'));

        $this->assertTrue(NetAddress::fromString('::/0')->contains('ffff::/16'));
        $this->assertFalse(NetAddress::fromString('::/1')->contains('ffff::/16'));
    }

    public function testToString()
    {
        $this->assertSame('1.2.3.4/15', NetAddress::fromString('1.2.3.4/15')->toString());
        $this->assertSame('1.2.3.4', NetAddress::fromString('1.2.3.4')->toString());
        $this->assertSame('1.2.3.4', NetAddress::fromString('1.2.3.4', 32)->toString());
        $this->assertSame('4:0::F', NetAddress::fromString('4:0::F')->toString());
        $this->assertSame('::1.2.3.4/123', NetAddress::fromString('::1.2.3.4/123')->toString());
    }

    public function testToCidrString()
    {
        $this->assertSame('1.2.3.4/15', NetAddress::fromString('1.2.3.4/15')->toCidrString());
        $this->assertSame('1.2.3.4/32', NetAddress::fromString('1.2.3.4')->toCidrString());
        $this->assertSame('4:0::F/128', NetAddress::fromString('4:0::F')->toCidrString());
        $this->assertSame('::1.2.3.4/128', NetAddress::fromString('::1.2.3.4')->toCidrString());
    }

    public function testToByteString()
    {
        $this->assertSame(chr(1) . chr(2) . chr(3) . chr(4), NetAddress::fromString('1.2.3.4/5')->toByteString());
        $this->assertSame(chr(0) . chr(0) . chr(0) . chr(0), NetAddress::fromString('0.0.0.0')->toByteString());
        $this->assertSame(str_repeat(chr(255), 4), NetAddress::fromString('255.255.255.255')->toByteString());

        $this->assertSame(str_repeat(chr(0), 16), NetAddress::fromString('::')->toByteString());
        $this->assertSame(
            chr(0) . chr(10) . chr(0xf7) . chr(0) . chr(1) . str_repeat(chr(0), 7) . chr(183) . chr(15) . chr(2) . chr(1),
            NetAddress::fromString('a:f700:100::183.15.2.1')->toByteString()
        );
    }

    public function testToInt()
    {
        $this->assertSame(1, NetAddress::fromString('0.0.0.1')->toInt());
        $this->assertSame(0, NetAddress::fromString('0.0.0.0')->toInt());
        $this->assertSame(16909060, NetAddress::fromString('1.2.3.4')->toInt());

        if (System::is32Bit()) {
            $this->assertSame(-1073732954, NetAddress::fromString('192.0.34.166')->toInt());
            $this->assertSame(-1, NetAddress::fromString('255.255.255.255')->toInt());
        } else {
            $this->assertSame(3221234342, NetAddress::fromString('192.0.34.166')->toInt());
            $this->assertSame(4294967295, NetAddress::fromString('255.255.255.255')->toInt());
        }

        $ipv6Address = NetAddress::fromString('2001:db8::1');
        try {
            $ipv6Address->toInt();
            $this->fail('\LogicException expected');
        } catch (\LogicException $e) {
        }
    }
}
