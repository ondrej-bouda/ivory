<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ParseException;
use PHPUnit\Framework\TestCase;

class MacAddrTest extends TestCase
{
    public function testFromString()
    {
        $a = MacAddr::fromString('08:00:2b:01:02:03');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08:00:2B:01:02:03');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08-00-2b-01-02-03');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b:010203');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b-010203');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('0800.2b01.0203');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('0800-2b01-0203');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        $a = MacAddr::fromString('08002b010203');
        self::assertSame('08:00:2b:01:02:03', $a->toString());

        try {
            MacAddr::fromString('7');
            self::fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr::fromString('08002b0102034');
            self::fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }

        try {
            MacAddr::fromString('08002b01020g');
            self::fail(ParseException::class . ' expected');
        } catch (ParseException $e) {
        }
    }
}
