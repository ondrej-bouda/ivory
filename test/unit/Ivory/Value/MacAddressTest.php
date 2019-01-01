<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ParseException;

class MacAddressTest extends \PHPUnit\Framework\TestCase
{
    public function testFromString()
    {
        $a = MacAddr::fromString('08:00:2b:01:02:03');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08:00:2B:01:02:03');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08-00-2b-01-02-03');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b:010203');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b-010203');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('0800.2b01.0203');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('0800-2b01-0203');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b010203');
        $this->assertSame('08:00:2b:01:02:03', $a->toString());

        try {
            MacAddr::fromString('7');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr::fromString('08002b0102034');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr::fromString('08002b01020g');
            $this->fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }
    }
}
