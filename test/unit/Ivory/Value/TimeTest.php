<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    private static function t($hour, $min, $sec)
    {
        return Time::fromPartsStrict($hour, $min, $sec);
    }

    public function testFromPartsStrict()
    {
        self::assertSame('08:45:03', Time::fromPartsStrict(8, 45, 3)->toString());
        self::assertSame('00:00:00', Time::fromPartsStrict(0, 0, 0)->toString());
        self::assertSame('24:00:00', Time::fromPartsStrict(24, 0, 0)->toString());
        self::assertSame('24:00:00', Time::fromPartsStrict(23, 59, 60)->toString());
        self::assertSame('11:00:00.123', Time::fromPartsStrict(10, 59, 60.123)->toString());
        self::assertSame('24:00:00.999', Time::fromPartsStrict(23, 59, 60.999)->toString());

        try {
            Time::fromPartsStrict(24, 0, 0.000001);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromPartsStrict(25, 0, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromPartsStrict(10, 60, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromPartsStrict(10, 59, 61);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }
    }

    public function testFromParts()
    {
        self::assertEquals(self::t(8, 45, 3), Time::fromParts(8, 45, 3));
        self::assertEquals(self::t(0, 0, 0), Time::fromParts(0, 0, 0));
        self::assertEquals(self::t(24, 0, 0), Time::fromParts(24, 0, 0));
        self::assertEquals(self::t(24, 0, 0), Time::fromParts(23, 59, 60));
        self::assertEquals(self::t(11, 0, 0.123), Time::fromParts(10, 59, 60.123));
        self::assertEquals(self::t(4, 11, 20), Time::fromParts(3, 70, 80));
        self::assertEquals(self::t(3, 58, 40), Time::fromParts(3, 60, -80));
        self::assertEquals(self::t(0, 0, 0), Time::fromParts(0, 30, -1800));

        try {
            Time::fromParts(24, 0, 0.000001);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromParts(25, 0, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromParts(23, 59, 60.000001);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromParts(0, 30, -1800.00001);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }
    }

    public function testEqualityOperator()
    {
        self::assertEquals(self::t(8, 45, 3), self::t(8, 45, 3));
        self::assertEquals(self::t(8, 46, 0), self::t(8, 45, 60));

        self::assertNotEquals(self::t(8, 45, 3), self::t(8, 45, 3.00000001));
    }

    public function testInequalityOperator()
    {
        // arguments for the less-than assertion: 1) the value to be the greater one, 2) the value to be the lesser one
        self::assertLessThan(self::t(8, 9, 10), self::t(8, 9, 9.9999999));
        self::assertLessThan(self::t(24, 0, 0), self::t(23, 59, 59));
    }

    public function testFromString()
    {
        self::assertEquals(self::t(8, 9, 0), Time::fromString('8:9'));
        self::assertEquals(self::t(8, 9, 0), Time::fromString('8:09'));
        self::assertEquals(self::t(8, 9, 0), Time::fromString('08:9'));
        self::assertEquals(self::t(8, 9, 0), Time::fromString('8:9:0'));

        self::assertEquals(self::t(8, 9, 1), Time::fromString('8:9:1'));
        self::assertEquals(self::t(8, 9, 1), Time::fromString('8:9:01'));
        self::assertEquals(self::t(8, 9, 1), Time::fromString('08:09:01'));
        self::assertEquals(self::t(8, 9, 1.123), Time::fromString('08:09:01.123'));

        self::assertEquals(self::t(8, 9, 60.123), Time::fromString('08:09:60.123'));

        self::assertEquals(self::t(0, 0, 0), Time::fromString('00:00:00.000'));
        self::assertEquals(self::t(24, 0, 0), Time::fromString('24:00:00.000'));

        try {
            Time::fromString('a');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Time::fromString('25:00:00');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('24:00:00.0000001');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('14:60:12');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('14:60:61.1234');
            self::fail();
        } catch (\OutOfRangeException $e) {
        }
    }

    public function testFromUnixTimestamp()
    {
        self::assertEquals(self::t(8, 9, 10), Time::fromUnixTimestamp(29350));
        self::assertEquals(self::t(8, 9, 10), Time::fromUnixTimestamp(288550));
        self::assertEquals(self::t(8, 9, 10.1234), Time::fromUnixTimestamp(288550.1234));

        self::assertEquals(self::t(23, 59, 30), Time::fromUnixTimestamp(-30));
        self::assertEquals(self::t(23, 59, 29.9), Time::fromUnixTimestamp(-30.1));
        self::assertEquals(self::t(23, 59, 30), Time::fromUnixTimestamp(-172830));
    }

    public function testFromDateTime()
    {
        self::assertEquals(
            self::t(8, 9, 10),
            Time::fromDateTime(\DateTime::createFromFormat('H:i:s', '08:09:10'))
        );
        self::assertEquals(
            self::t(8, 9, 10),
            Time::fromDateTime(new \DateTimeImmutable('@' . gmmktime(8, 9, 10, 3, 14, 2016)))
        );
    }

    public function testGetters()
    {
        self::assertSame(8, self::t(8, 9, 10)->getHours());
        self::assertSame(0, self::t(0, 9, 10)->getHours());
        self::assertSame(24, self::t(24, 0, 0)->getHours());

        self::assertSame(9, self::t(8, 9, 10)->getMinutes());

        self::assertSame(10, self::t(8, 9, 10)->getSeconds());
        self::assertSame(0, self::t(8, 9, 0)->getSeconds());
        self::assertSame(0, self::t(8, 9, 60)->getSeconds());
        self::assertSame(0.1234, self::t(8, 9, 60.1234)->getSeconds());
    }

    public function testToString()
    {
        self::assertSame('08:45:03', self::t(8, 45, 3)->toString());
        self::assertSame('15:45:03.1234', self::t(15, 45, 3.1234)->toString());
        self::assertSame('15:46:00', self::t(15, 45, 60)->toString());
    }

    public function testFormat()
    {
        self::assertSame('08:45:03', self::t(8, 45, 3)->format('H:i:s'));
        self::assertSame('15:45:03.123400', self::t(15, 45, 3.1234)->format('H:i:s.u'));
        self::assertSame('15:45:03.012340', self::t(15, 45, 3.01234)->format('H:i:s.u'));
        self::assertSame('15:46:00.000000', self::t(15, 45, 60)->format('H:i:s.u'));

        self::assertSame('15:45:03|123400|123400', self::t(15, 45, 3.1234)->format('H:i:s|u|u'));

        self::assertSame('15:45:03 \\u 123400', self::t(15, 45, 3.1234)->format('H:i:s \\\\\\u u'));

        self::assertSame('1.1.1970', self::t(8, 45, 3)->format('j.n.Y'));
    }

    public function testToUnixTimestamp()
    {
        self::assertSame(gmmktime(8, 9, 10, 1, 1, 1970), self::t(8, 9, 10)->toUnixTimestamp());
        self::assertSame(gmmktime(8, 9, 10, 1, 1, 1970) + .1234, self::t(8, 9, 10.1234)->toUnixTimestamp());
        self::assertSame(gmmktime(8, 9, 10, 3, 14, 2016), self::t(8, 9, 10)->toUnixTimestamp('2016-03-14'));
        self::assertSame(
            gmmktime(8, 9, 10, 3, 14, 2016),
            self::t(8, 9, 10)->toUnixTimestamp(Date::fromISOString('2016-03-14'))
        );

        try {
            self::t(8, 9, 10)->toUnixTimestamp('abc');
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10)->toUnixTimestamp(Date::infinity());
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10)->toUnixTimestamp(Date::minusInfinity());
            self::fail();
        } catch (\InvalidArgumentException $e) {
        }
    }
}
