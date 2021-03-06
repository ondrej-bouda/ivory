<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class TimeTzTest extends TestCase
{
    private static function t($hour, $min, $sec, $offset): TimeTz
    {
        return TimeTz::fromPartsStrict($hour, $min, $sec, $offset);
    }

    public function testFromPartsStrict()
    {
        self::assertSame('08:45:03+0200', TimeTz::fromPartsStrict(8, 45, 3, 7200)->toString());
        self::assertSame('00:00:00+1459', TimeTz::fromPartsStrict(0, 0, 0, 53940)->toString());
        self::assertSame('24:00:00-1459', TimeTz::fromPartsStrict(24, 0, 0, -53940)->toString());
        self::assertSame('24:00:00+0000', TimeTz::fromPartsStrict(23, 59, 60, 0)->toString());
        self::assertSame('11:00:00.123-0830', TimeTz::fromPartsStrict(10, 59, 60.123, -30600)->toString());
        self::assertSame('24:00:00.999-0001', TimeTz::fromPartsStrict(23, 59, 60.999, -60)->toString());

        try {
            TimeTz::fromPartsStrict(24, 0, 0.000001, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromPartsStrict(25, 0, 0, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromPartsStrict(10, 60, 0, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromPartsStrict(10, 59, 61, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }
    }

    public function testFromParts()
    {
        self::assertEquals(self::t(8, 45, 3, 0), TimeTz::fromParts(8, 45, 3, 0));
        self::assertEquals(self::t(8, 45, 3, -53940), TimeTz::fromParts(8, 45, 3, -53940));
        self::assertEquals(self::t(0, 0, 0, 0), TimeTz::fromParts(0, 0, 0, 0));
        self::assertEquals(self::t(0, 0, 0, -53940), TimeTz::fromParts(0, 0, 0, -53940));
        self::assertEquals(self::t(24, 0, 0, 0), TimeTz::fromParts(24, 0, 0, 0));
        self::assertEquals(self::t(24, 0, 0, 53940), TimeTz::fromParts(24, 0, 0, 53940));
        self::assertEquals(self::t(24, 0, 0, 0), TimeTz::fromParts(23, 59, 60, 0));
        self::assertEquals(self::t(24, 0, 0, 53940), TimeTz::fromParts(23, 59, 60, 53940));
        self::assertEquals(self::t(11, 0, 0.123, 12345), TimeTz::fromParts(10, 59, 60.123, 12345));
        self::assertEquals(self::t(4, 11, 20, 12345), TimeTz::fromParts(3, 70, 80, 12345));
        self::assertEquals(self::t(3, 58, 40, 12345), TimeTz::fromParts(3, 60, -80, 12345));
        self::assertEquals(self::t(0, 0, 0, 12345), TimeTz::fromParts(0, 30, -1800, 12345));

        try {
            TimeTz::fromParts(24, 0, 0.000001, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromParts(25, 0, 0, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromParts(23, 59, 60.000001, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromParts(0, 30, -1800.00001, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }
    }

    public function testEqualityOperator()
    {
        self::assertEquals(self::t(8, 45, 3, 7200), self::t(8, 45, 3, 7200));
        self::assertEquals(self::t(8, 46, 0, 0), self::t(8, 45, 60, 0));

        self::assertNotEquals(self::t(8, 45, 3, 0), self::t(8, 45, 3.00000001, 0));
        self::assertNotEquals(self::t(8, 45, 3, 3600), self::t(8, 45, 3, 7200));
        self::assertNotEquals(self::t(7, 45, 3, 3600), self::t(8, 45, 3, 7200));
    }

    public function testOccursAt()
    {
        self::assertTrue(self::t(23, 59, 59, 0)->occursAt(self::t(23, 59, 59, 0)));
        self::assertTrue(self::t(11, 12, 13, 7200)->occursAt(self::t(10, 12, 13, 3600)));

        self::assertFalse(self::t(8, 9, 9.9999999, 0)->occursAt(self::t(8, 9, 10, 0)));
        self::assertFalse(self::t(23, 59, 59, 0)->occursAt(self::t(24, 0, 0, 0)));

        self::assertFalse(self::t(11, 12, 13, 7200)->occursAt(self::t(11, 12, 13, 3600)));
    }

    public function testOccursBefore()
    {
        self::assertFalse(self::t(23, 59, 59, 0)->occursBefore(self::t(23, 59, 59, 0)));
        self::assertFalse(self::t(11, 12, 13, 7200)->occursBefore(self::t(10, 12, 13, 3600)));

        self::assertTrue(self::t(8, 9, 9.9999999, 0)->occursBefore(self::t(8, 9, 10, 0)));
        self::assertTrue(self::t(23, 59, 59, 0)->occursBefore(self::t(24, 0, 0, 0)));

        self::assertTrue(self::t(11, 12, 13, 7200)->occursBefore(self::t(11, 12, 13, 3600)));
        self::assertTrue(self::t(11, 12, 12, 7200)->occursBefore(self::t(11, 12, 13, 3600)));

        self::assertFalse(self::t(8, 9, 10, 0)->occursBefore(self::t(8, 9, 9.9999999, 0)));
        self::assertFalse(self::t(24, 0, 0, 0)->occursBefore(self::t(23, 59, 59, 0)));

        self::assertFalse(self::t(11, 12, 13, 3600)->occursBefore(self::t(11, 12, 13, 7200)));
        self::assertFalse(self::t(11, 12, 13, 3600)->occursBefore(self::t(11, 12, 12, 7200)));
    }

    public function testOccurAfter()
    {
        self::assertFalse(self::t(23, 59, 59, 0)->occursAfter(self::t(23, 59, 59, 0)));
        self::assertFalse(self::t(11, 12, 13, 7200)->occursAfter(self::t(10, 12, 13, 3600)));

        self::assertTrue(self::t(8, 9, 10, 0)->occursAfter(self::t(8, 9, 9.9999999, 0)));
        self::assertTrue(self::t(24, 0, 0, 0)->occursAfter(self::t(23, 59, 59, 0)));

        self::assertTrue(self::t(11, 12, 13, 3600)->occursAfter(self::t(11, 12, 13, 7200)));
        self::assertTrue(self::t(11, 12, 13, 3600)->occursAfter(self::t(11, 12, 12, 7200)));

        self::assertFalse(self::t(8, 9, 9.9999999, 0)->occursAfter(self::t(8, 9, 10, 0)));
        self::assertFalse(self::t(23, 59, 59, 0)->occursAfter(self::t(24, 0, 0, 0)));

        self::assertFalse(self::t(11, 12, 13, 7200)->occursAfter(self::t(11, 12, 13, 3600)));
        self::assertFalse(self::t(11, 12, 12, 7200)->occursAfter(self::t(11, 12, 13, 3600)));
    }

    public function testFromString()
    {
        self::assertEquals(self::t(8, 9, 0, 7200), TimeTz::fromString('8:9+2'));
        self::assertEquals(self::t(8, 9, 0, -7200), TimeTz::fromString('8:09-02'));
        self::assertEquals(self::t(8, 9, 0, 9000), TimeTz::fromString('08:9+0230'));
        self::assertEquals(self::t(8, 9, 0, 0), TimeTz::fromString('8:9:0+00'));

        self::assertEquals(self::t(8, 9, 1, (new \DateTime())->getOffset()), TimeTz::fromString('8:9:1'));
        self::assertEquals(self::t(8, 9, 1, -30600), TimeTz::fromString('8:9:01-8:30'));
        self::assertEquals(self::t(8, 9, 1, 0), TimeTz::fromString('08:09:01-00'));
        self::assertEquals(self::t(8, 9, 1.123, 0), TimeTz::fromString('08:09:01.123+0'));

        self::assertEquals(self::t(8, 9, 60.123, 3600), TimeTz::fromString('08:09:60.123+1:00'));

        self::assertEquals(self::t(0, 0, 0, -7200), TimeTz::fromString('00:00:00.000-200'));
        self::assertEquals(self::t(24, 0, 0, -7200), TimeTz::fromString('24:00:00.000-200'));

        self::assertEquals(self::t(14, 15, 0, 0), TimeTz::fromString('14:15:00Z'));

        try {
            TimeTz::fromString('a');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            TimeTz::fromString('25:00:00');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromString('24:00:00.0000001');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromString('14:60:12');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromString('14:60:61.1234');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            TimeTz::fromString('14:15:00+a');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            TimeTz::fromString('14:15:00+13:5');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            TimeTz::fromString('14:15:00UTC');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromDateTime()
    {
        self::assertEquals(
            self::t(8, 9, 10, (new \DateTime())->getOffset()),
            TimeTz::fromDateTime(\DateTime::createFromFormat('H:i:s', '08:09:10'))
        );

        self::assertEquals(
            self::t(8, 9, 10, 0),
            TimeTz::fromDateTime(new \DateTimeImmutable('@' . gmmktime(8, 9, 10, 3, 14, 2016)))
        );
    }

    public function testGetters()
    {
        self::assertSame(8, self::t(8, 9, 10, 3600)->getHours());
        self::assertSame(0, self::t(0, 9, 10, 3600)->getHours());
        self::assertSame(24, self::t(24, 0, 0, 3600)->getHours());

        self::assertSame(9, self::t(8, 9, 10, 3600)->getMinutes());

        self::assertSame(10, self::t(8, 9, 10, 3600)->getSeconds());
        self::assertSame(0, self::t(8, 9, 0, 3600)->getSeconds());
        self::assertSame(0, self::t(8, 9, 60, 3600)->getSeconds());
        self::assertSame(0.1234, self::t(8, 9, 60.1234, 3600)->getSeconds());

        self::assertSame(3600, self::t(8, 9, 10, 3600)->getOffset());
        self::assertSame(-1800, self::t(8, 9, 10, -1800)->getOffset());

        self::assertSame('+0000', self::t(8, 9, 10, 0)->getOffsetISOString());
        self::assertSame('+0100', self::t(8, 9, 10, 3600)->getOffsetISOString());
        self::assertSame('+1300', self::t(8, 9, 10, 46800)->getOffsetISOString());
        self::assertSame('-0230', self::t(8, 9, 10, -9000)->getOffsetISOString());
        self::assertSame('-0230', self::t(8, 9, 10, -9001)->getOffsetISOString());
        self::assertSame('-0229', self::t(8, 9, 10, -8999)->getOffsetISOString());
    }

    public function testToString()
    {
        self::assertSame('08:45:03+0000', self::t(8, 45, 3, 0)->toString());
        self::assertSame('15:45:03.1234+0230', self::t(15, 45, 3.1234, 9000)->toString());
        self::assertSame('15:46:00-0059', self::t(15, 45, 60, -3599)->toString());
    }

    public function testFormat()
    {
        self::assertSame('08:45:03 0 +0000 +00:00', self::t(8, 45, 3, 0)->format('H:i:s Z O P'));
        self::assertSame('08:45:03 3600 +0100 +01:00', self::t(8, 45, 3, 3600)->format('H:i:s Z O P'));
        self::assertSame(
            '15:45:03.012340 -9000 -0230 -02:30',
            self::t(15, 45, 3.01234, -9000)->format('H:i:s.u Z O P')
        );
        self::assertSame('15:46:00.000000 46800 +1300 +13:00', self::t(15, 45, 60, 46800)->format('H:i:s.u Z O P'));

        self::assertSame('15:45:03|123400|123400', self::t(15, 45, 3.1234, -46800)->format('H:i:s|u|u'));

        self::assertSame(
            '15:45:03 \\u 123400 \\O \\P \\Z',
            self::t(15, 45, 3.1234, -46800)->format('H:i:s \\\\\\u u \\\\\\O \\\\\\P \\\\\\Z')
        );

        self::assertSame('1.1.1970', self::t(8, 45, 3, 0)->format('j.n.Y'));
    }

    public function testToUnixTimestamp()
    {
        self::assertSame(
            gmmktime(8, 9, 10, 1, 1, 1970),
            self::t(8, 9, 10, 0)->toUnixTimestamp()
        );
        self::assertSame(
            gmmktime(8, 9, 10, 1, 1, 1970) + .1234,
            self::t(8, 9, 10.1234, 0)->toUnixTimestamp()
        );
        self::assertSame(
            gmmktime(7, 9, 10, 3, 14, 2016),
            self::t(8, 9, 10, 3600)->toUnixTimestamp('2016-03-14')
        );
        self::assertSame(
            gmmktime(9, 39, 10, 3, 14, 2016),
            self::t(8, 9, 10, -5400)->toUnixTimestamp(Date::fromISOString('2016-03-14'))
        );

        try {
            self::t(8, 9, 10, 0)->toUnixTimestamp('abc');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10, 0)->toUnixTimestamp(Date::infinity());
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10, 0)->toUnixTimestamp(Date::minusInfinity());
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }
    }
}
