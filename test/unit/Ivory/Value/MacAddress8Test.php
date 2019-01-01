<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ParseException;

class MacAddress8Test extends \PHPUnit\Framework\TestCase
{
    public function testFromString()
    {
        $a = MacAddr8::fromString('08:00:2b:01:02:03:04:05');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08:00:2B:01:02:03:04:05');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08-00-2b-01-02-03-04-05');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08002b:0102030405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08002b-0102030405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('0800.2b01.0203.0405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('0800-2b01-0203-0405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08002b01:02030405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08002b0102030405');
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString("\t08:00:2b:01:02:03:04:05 ");
        $this->assertSame('08:00:2b:01:02:03:04:05', $a->toString());

        $a = MacAddr8::fromString('08:00:2b:01:02:03');
        $this->assertSame('08:00:2b:ff:fe:01:02:03', $a->toString());

        try {
            MacAddr8::fromString('7');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr8::fromString('08002b01020304056');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr8::fromString('08002b010203040g');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }
    }

    public function testFrom6ByteMacAddr()
    {
        $a6 = MacAddr::fromString('08:00:2b:01:02:03');
        $a8 = MacAddr8::from6ByteMacAddr($a6);
        $this->assertSame('08:00:2b:ff:fe:01:02:03', $a8->toString());
    }

    public function testFrom6ByteMacAddrForIp6()
    {
        $a6 = MacAddr::fromString('08:00:2b:01:02:03');
        $a8 = MacAddr8::from6ByteMacAddrForIp6($a6);
        $this->assertSame('0a:00:2b:ff:fe:01:02:03', $a8->toString());

        $a6 = MacAddr::fromString('0a:00:2b:01:02:03');
        $a8 = MacAddr8::from6ByteMacAddrForIp6($a6);
        $this->assertSame('0a:00:2b:ff:fe:01:02:03', $a8->toString());
    }
}
