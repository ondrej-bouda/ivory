<?php
namespace Ivory\Value;

class DateTimeTest extends \PHPUnit_Framework_TestCase
{
    private static function dt($year, $month, $day, $hour, $min, $sec)
    {
        return DateTime::fromPartsStrict($year, $month, $day, $hour, $min, $sec);
    }

    public function testEqualityOperator()
    {
        $this->assertEquals(self::dt(2015, 1, 30, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertEquals(self::dt(12345, 1, 30, 0, 0, 0), self::dt(12345, 1, 30, 0, 0, 0));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 0, 0), self::dt(-2015, 1, 30, 24, 0, 0));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 1, 0), self::dt(-2015, 1, 30, 24, 0, 60));
        $this->assertEquals(self::dt(2015, 1, 30, 12, 34, 56.123456), self::dt(2015, 1, 30, 12, 34, 56.123456));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 1, .123456), self::dt(-2015, 1, 30, 24, 0, 60.123456));
        $this->assertEquals(DateTime::infinity(), DateTime::infinity());
        $this->assertEquals(DateTime::minusInfinity(), DateTime::minusInfinity());

        $this->assertNotEquals(DateTime::infinity(), DateTime::minusInfinity());
        $this->assertNotEquals(DateTime::infinity(), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertNotEquals(self::dt(2015, 1, 29, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertNotEquals(self::dt(2015, 1, 30, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56.000001));
    }

    public function testNow()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = DateTime::now();
            $end = time();
            $this->assertLessThanOrEqual($dt->toTimestamp(), $start);
            $this->assertLessThanOrEqual($end, $dt->toTimestamp());
            $this->assertEquals(0, $dt->format('u'));
        }
    }

    public function testNowMicro()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        $hitNonZeroMicrosec = false;
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = DateTime::nowMicro();
            $end = time();
            $this->assertLessThanOrEqual($dt->toTimestamp(), $start);
            $this->assertLessThanOrEqual($end, $dt->toTimestamp());
            if ($dt->format('u') != 0) {
                $hitNonZeroMicrosec = true;
                break; // OK - non-zero fractional part
            }
        }
        $this->assertTrue($hitNonZeroMicrosec);
    }

    public function testFromISOString()
    {
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), DateTime::fromISOString('2016-03-01T14:30:16Z'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), DateTime::fromISOString('2016-03-01T14:30:16'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), DateTime::fromISOString('2016-03-01T14:30:16.0-0800'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.1), DateTime::fromISOString('2016-03-01T14:30:16.1+08:00'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), DateTime::fromISOString('2016-03-01T14:30:16-08'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.1234), DateTime::fromISOString('2016-03-01T14:30:16.1234'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), DateTime::fromISOString('2016-03-01 14:30:16'));
    }

    public function testAddSecond()
    {
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.41), self::dt(2016, 3, 1, 14, 30, 16.3)->addSecond(.11));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 17.01), self::dt(2016, 3, 1, 14, 30, 16.9)->addSecond(.11));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 15.01), self::dt(2016, 3, 1, 14, 30, 16)->addSecond(-.99));
    }
}
