<?php
namespace Ivory\Value;

class DateTest extends \PHPUnit_Framework_TestCase
{
    private static function d($year, $month, $day)
    {
        return Date::fromPartsStrict($year, $month, $day);
    }

    public function testEqualityOperator()
    {
        $this->assertEquals(self::d(2015, 1, 30), self::d(2015, 1, 30));
        $this->assertEquals(self::d(12345, 1, 30), self::d(12345, 1, 30));
        $this->assertEquals(self::d(-2015, 1, 30), self::d(-2015, 1, 30));
        $this->assertEquals(Date::infinity(), Date::infinity());
        $this->assertEquals(Date::minusInfinity(), Date::minusInfinity());

        $this->assertNotEquals(Date::infinity(), Date::minusInfinity());
        $this->assertNotEquals(Date::infinity(), self::d(2015, 1, 30));
        $this->assertNotEquals(self::d(2015, 1, 29), self::d(2015, 1, 30));
    }

    public function testInequalityOperator()
    {
        // arguments for the less-than assertion: 1) the value to be the greater one, 2) the value to be the lesser one
        $this->assertLessThan(self::d(2016, 2, 28), self::d(2016, 2, 27));
        $this->assertLessThan(self::d(2016, 2, 28), self::d(-1, 2, 27));
        $this->assertLessThan(self::d(-12345, 2, 28), self::d(-12345, 2, 27));
        $this->assertLessThan(Date::infinity(), self::d(2016, 2, 27));
        $this->assertLessThan(self::d(2016, 2, 27), Date::minusInfinity());
    }

    public function testFromISOString()
    {
        $this->assertEquals(self::d(2016, 2, 27), Date::fromISOString('2016-02-27'));
        $this->assertEquals(self::d(-2016, 2, 27), Date::fromISOString('-2015-02-27'));
        $this->assertEquals(self::d(12345, 6, 7), Date::fromISOString('12345-06-07'));
        $this->assertEquals(self::d(-1, 2, 27), Date::fromISOString('0000-02-27'));
        $this->assertEquals(self::d(-2, 2, 27), Date::fromISOString('-0001-02-27'));
    }

    public function testFromUnixTimestamp()
    {
        $this->assertEquals(self::d(2016, 2, 27), Date::fromUnixTimestamp(gmmktime(0, 0, 0, 2, 27, 2016)));
        $this->assertEquals(self::d(2016, 2, 27), Date::fromUnixTimestamp(gmmktime(23, 59, 59, 2, 27, 2016)));
    }

    public function testFromDateTime()
    {
        $utc = new \DateTimeZone('UTC');

        $this->assertEquals(self::d(2016,  2, 27), Date::fromDateTime(new \DateTime('2016-02-27', $utc)));
        $this->assertEquals(self::d(2016,  2, 27), Date::fromDateTime(new \DateTime('2016-02-27 15:48:12.1234', $utc)));

        $this->assertEquals(self::d(2016,  2, 27), Date::fromDateTime(new \DateTimeImmutable('2016-02-27', $utc)));
        $this->assertEquals(self::d(2016,  2, 27), Date::fromDateTime(new \DateTimeImmutable('2016-02-27 15:48:12.1234', $utc)));
    }

    public function testFromParts()
    {
        $this->assertEquals(self::d(2016, 2, 27), Date::fromParts(2016, 2, 27));
        $this->assertEquals(self::d(-2016, 2, 27), Date::fromParts(-2016, 2, 27));
        $this->assertEquals(self::d(2016, 3, 1), Date::fromParts(2016, 2, 30));
        $this->assertEquals(self::d(2016, 3, 5), Date::fromParts(2016, 2, 34));
        $this->assertEquals(self::d(2017, 1, 31), Date::fromParts(2016, 14, 0));
        $this->assertEquals(self::d(2016, 12, 26), Date::fromParts(2016, 13, -5));
        $this->assertEquals(self::d(2014, 11, 1), Date::fromParts(2016, -13, 1));

        try {
            Date::fromParts(0, 1, 1);
            $this->fail('Year number 0 shall be treated as invalid');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromPartsStrict()
    {
        $this->assertEquals(self::d(2016, 2, 27), Date::fromPartsStrict(2016, 2, 27));
        $this->assertEquals(self::d(-2016, 2, 27), Date::fromPartsStrict(-2016, 2, 27));

        try {
            Date::fromPartsStrict(2016, 2, 30);
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 2, 34);
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 14, 0);
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 13, -5);
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, -13, 1);
            $this->fail();
        }
        catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(0, 1, 1);
            $this->fail('Year number 0 shall be treated as invalid');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testAddParts()
    {
        $this->assertEquals(self::d(2016, 2, 27), self::d(2016, 2, 25)->addParts(0, 0, 2));
        $this->assertEquals(self::d(2016, 3, 2), self::d(2016, 2, 25)->addParts(0, 0, 6));
        $this->assertEquals(self::d(2012, 2, 29), self::d(2015, 1, 25)->addParts(-3, 1, 4));

        $this->assertEquals(self::d(2015, 7, 1), self::d(2015, 5, 31)->addParts(0, 1, 0));
        $this->assertEquals(self::d(2015, 7, 2), self::d(2015, 5, 31)->addParts(0, 1, 1));
        $this->assertEquals(self::d(2015, 8, 1), self::d(2015, 5, 31)->addParts(0, 2, 1));
        $this->assertEquals(self::d(2015, 3, 29), self::d(2015, 2, 28)->addParts(0, 1, 1));
        $this->assertEquals(self::d(2016, 2, 29), self::d(2015, 2, 28)->addParts(1, 0, 1));
        $this->assertEquals(self::d(2017, 3, 1), self::d(2016, 2, 28)->addParts(1, 0, 1));

        $this->assertEquals(Date::infinity(), Date::infinity()->addParts(1, 2, 3));
        $this->assertEquals(Date::minusInfinity(), Date::minusInfinity()->addParts(1, 2, 3));
    }

    public function testToParts()
    {
        $this->assertSame([2016, 1, 30], self::d(2016, 1, 30)->toParts());
        $this->assertSame([-1, 1, 30], self::d(-1, 1, 30)->toParts());
        $this->assertSame([-2016, 1, 30], self::d(-2016, 1, 30)->toParts());
        $this->assertSame([12345, 1, 30], self::d(12345, 1, 30)->toParts());
        $this->assertNull(Date::infinity()->toParts());
        $this->assertNull(Date::minusInfinity()->toParts());
    }

    public function testToISOString()
    {
        $this->assertSame('2016-01-30', self::d(2016, 1, 30)->toISOString());
        $this->assertSame('0000-01-30', self::d(-1, 1, 30)->toISOString());
        $this->assertSame('-2015-01-30', self::d(-2016, 1, 30)->toISOString());
        $this->assertSame('12345-01-30', self::d(12345, 1, 30)->toISOString());
        $this->assertNull(Date::infinity()->toISOString());
        $this->assertNull(Date::minusInfinity()->toISOString());
    }

    public function testToUnixTimestamp()
    {
        $this->assertSame(gmmktime(0, 0, 0, 1, 30, 2016), self::d(2016, 1, 30)->toUnixTimestamp());
        $this->assertSame(gmmktime(0, 0, 0, 1, 30, 2016), Date::fromUnixTimestamp(gmmktime(12, 55, 0, 1, 30, 2016))->toUnixTimestamp());
        $this->assertNull(Date::infinity()->toUnixTimestamp());
        $this->assertNull(Date::minusInfinity()->toUnixTimestamp());
    }
}
