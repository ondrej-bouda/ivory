<?php
declare(strict_types=1);
namespace Ivory\Value;

class TimestampTest extends \PHPUnit\Framework\TestCase
{
    private static function dt($year, $month, $day, $hour, $min, $sec)
    {
        return Timestamp::fromPartsStrict($year, $month, $day, $hour, $min, $sec);
    }

    public function testEqualityOperator()
    {
        $this->assertEquals(self::dt(2015, 1, 30, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertEquals(self::dt(12345, 1, 30, 0, 0, 0), self::dt(12345, 1, 30, 0, 0, 0));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 0, 0), self::dt(-2015, 1, 30, 24, 0, 0));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 1, 0), self::dt(-2015, 1, 30, 24, 0, 60));
        $this->assertEquals(self::dt(2015, 1, 30, 12, 34, 56.123456), self::dt(2015, 1, 30, 12, 34, 56.123456));
        $this->assertEquals(self::dt(-2015, 1, 31, 0, 1, .123456), self::dt(-2015, 1, 30, 24, 0, 60.123456));
        $this->assertEquals(Timestamp::infinity(), Timestamp::infinity());
        $this->assertEquals(Timestamp::minusInfinity(), Timestamp::minusInfinity());

        $this->assertNotEquals(Timestamp::infinity(), Timestamp::minusInfinity());
        $this->assertNotEquals(Timestamp::infinity(), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertNotEquals(self::dt(2015, 1, 29, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56));
        $this->assertNotEquals(self::dt(2015, 1, 30, 12, 34, 56), self::dt(2015, 1, 30, 12, 34, 56.000001));
    }

    public function testNow()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        $hitNonZeroMicrosec = false;
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = Timestamp::now();
            $end = time();
            $this->assertLessThanOrEqual($start, $dt->toUnixTimestamp());
            $this->assertLessThanOrEqual($dt->toUnixTimestamp(), $end);
            // the precision should be greater than just mere seconds
            if ($dt->format('u') != 0) {
                $hitNonZeroMicrosec = true;
                break; // OK - non-zero fractional part
            }
        }
        $this->assertTrue($hitNonZeroMicrosec);
    }

    public function testFromISOString()
    {
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), Timestamp::fromISOString('2016-03-01T14:30:16Z'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), Timestamp::fromISOString('2016-03-01T14:30:16'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), Timestamp::fromISOString('2016-03-01T14:30:16.0-08:00'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.1), Timestamp::fromISOString('2016-03-01T14:30:16.1+0800'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), Timestamp::fromISOString('2016-03-01T14:30:16-08'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.1234), Timestamp::fromISOString('2016-03-01T14:30:16.1234'));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16), Timestamp::fromISOString('2016-03-01 14:30:16'));
    }

    public function testAddSecond()
    {
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 16.41), self::dt(2016, 3, 1, 14, 30, 16.3)->addSecond(.11));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 17.01), self::dt(2016, 3, 1, 14, 30, 16.9)->addSecond(.11));
        $this->assertEquals(self::dt(2016, 3, 1, 14, 30, 15.01), self::dt(2016, 3, 1, 14, 30, 16)->addSecond(-.99));
    }

    public function testToParts()
    {
        $this->assertSame([2016, 3, 1, 14, 30, 16], Timestamp::fromISOString('2016-03-01T14:30:16')->toParts());

        $partsWithFracSec = Timestamp::fromISOString('2016-03-01T14:30:16.0123')->toParts();
        $this->assertSame([2016, 3, 1, 14, 30], array_slice($partsWithFracSec, 0, 5));
        $this->assertEquals(16.0123, $partsWithFracSec[5], '', 1e-9);
    }
}
