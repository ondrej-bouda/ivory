<?php
namespace Ivory\Value;

class TimeTest extends \PHPUnit_Framework_TestCase
{
    private static function t($hour, $min, $sec)
    {
        return Time::fromTimestamp($hour * 60 * 60 + $min * 60 + $sec);
    }

    public function testEqualityOperator()
    {
        $this->assertEquals(self::t(8, 45, 3), self::t(8, 45, 3));
        $this->assertEquals(self::t(8, 46, 0), self::t(8, 45, 60));

        $this->assertNotEquals(self::t(8, 45, 3), self::t(8, 45, 3.00000001));
    }

    public function testInequalityOperator()
    {
        // arguments for the less-than assertion: 1) the value to be the greater one, 2) the value to be the lesser one
        $this->assertLessThan(self::t(8, 9, 10), self::t(8, 9, 9.9999999));
        $this->assertLessThan(self::t(24, 0, 0), self::t(23, 59, 59));
    }

    public function testFromString()
    {
        $this->assertEquals(self::t(8, 9, 0), Time::fromString('8:9'));
        $this->assertEquals(self::t(8, 9, 0), Time::fromString('8:09'));
        $this->assertEquals(self::t(8, 9, 0), Time::fromString('08:9'));
        $this->assertEquals(self::t(8, 9, 0), Time::fromString('8:9:0'));

        $this->assertEquals(self::t(8, 9, 1), Time::fromString('8:9:1'));
        $this->assertEquals(self::t(8, 9, 1), Time::fromString('8:9:01'));
        $this->assertEquals(self::t(8, 9, 1), Time::fromString('08:09:01'));
        $this->assertEquals(self::t(8, 9, 1.123), Time::fromString('08:09:01.123'));

        $this->assertEquals(self::t(8, 9, 60.123), Time::fromString('08:09:60.123'));

        $this->assertEquals(self::t(0, 0, 0), Time::fromString('00:00:00.000'));
        $this->assertEquals(self::t(24, 0, 0), Time::fromString('24:00:00.000'));

        try {
            Time::fromString('a');
            $this->fail();
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            Time::fromString('25:00:00');
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('24:00:00.0000001');
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('14:60:12');
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Time::fromString('14:60:61.1234');
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }
    }

    public function testFromTimestamp()
    {
        $this->assertEquals(self::t(8, 9, 10), Time::fromTimestamp(29350));
        $this->assertEquals(self::t(8, 9, 10), Time::fromTimestamp(288550));
        $this->assertEquals(self::t(8, 9, 10.1234), Time::fromTimestamp(288550.1234));

        $this->assertEquals(self::t(23, 59, 30), Time::fromTimestamp(-30));
        $this->assertEquals(self::t(23, 59, 29.9), Time::fromTimestamp(-30.1));
        $this->assertEquals(self::t(23, 59, 30), Time::fromTimestamp(-172830));
    }

    public function testFromDateTime()
    {
        $this->assertEquals(self::t(8, 9, 10), Time::fromDateTime(\DateTime::createFromFormat('H:i:s', '08:09:10')));
        $this->assertEquals(self::t(8, 9, 10), Time::fromDateTime(new \DateTimeImmutable('@' . gmmktime(8, 9, 10, 3, 14, 2016))));
    }

    public function testGetters()
    {
        $this->assertSame(8, self::t(8, 9, 10)->getHours());
        $this->assertSame(0, self::t(0, 9, 10)->getHours());
        $this->assertSame(24, self::t(24, 0, 0)->getHours());

        $this->assertSame(9, self::t(8, 9, 10)->getMinutes());

        $this->assertSame(10, self::t(8, 9, 10)->getSeconds());
        $this->assertSame(0, self::t(8, 9, 0)->getSeconds());
        $this->assertSame(0, self::t(8, 9, 60)->getSeconds());
        $this->assertSame(0.1234, self::t(8, 9, 60.1234)->getSeconds());
    }

    public function testToString()
    {
        $this->assertSame('08:45:03', self::t(8, 45, 3)->toString());
        $this->assertSame('15:45:03.1234', self::t(15, 45, 3.1234)->toString());
        $this->assertSame('15:46:00', self::t(15, 45, 60)->toString());
    }

    public function testFormat()
    {
        $this->assertSame('08:45:03', self::t(8, 45, 3)->format('H:i:s'));
        $this->assertSame('15:45:03.1234', self::t(15, 45, 3.1234)->format('H:i:s.u'));
        $this->assertSame('15:46:00.0', self::t(15, 45, 60)->format('H:i:s.u'));

        $this->assertSame('15:45:03|1234|1234', self::t(15, 45, 3.1234)->format('H:i:s|u|u'));

        $this->assertSame('15:45:03 \\u 1234', self::t(15, 45, 3.1234)->format('H:i:s \\\\\\u u'));
    }

    public function testToTimestamp()
    {
        $this->assertSame(gmmktime(8, 9, 10, 1, 1, 1970), self::t(8, 9, 10)->toTimestamp());
        $this->assertSame(gmmktime(8, 9, 10, 1, 1, 1970) + .1234, self::t(8, 9, 10.1234)->toTimestamp());
        $this->assertSame(gmmktime(8, 9, 10, 3, 14, 2016), self::t(8, 9, 10)->toTimestamp('2016-03-14'));
        $this->assertSame(gmmktime(8, 9, 10, 3, 14, 2016), self::t(8, 9, 10)->toTimestamp(Date::fromISOString('2016-03-14')));

        try {
            self::t(8, 9, 10)->toTimestamp('abc');
            $this->fail();
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10)->toTimestamp(Date::infinity());
            $this->fail();
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            self::t(8, 9, 10)->toTimestamp(Date::minusInfinity());
            $this->fail();
        }
        catch (\InvalidArgumentException $e) {
        }
    }
}