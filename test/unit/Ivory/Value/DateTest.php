<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    private static function d($year, $month, $day): Date
    {
        return Date::fromPartsStrict($year, $month, $day);
    }

    public function testEqualityOperator()
    {
        self::assertEquals(self::d(2015, 1, 30), self::d(2015, 1, 30));
        self::assertEquals(self::d(12345, 1, 30), self::d(12345, 1, 30));
        self::assertEquals(self::d(-2015, 1, 30), self::d(-2015, 1, 30));
        self::assertEquals(Date::infinity(), Date::infinity());
        self::assertEquals(Date::minusInfinity(), Date::minusInfinity());

        self::assertNotEquals(Date::infinity(), Date::minusInfinity());
        self::assertNotEquals(Date::infinity(), self::d(2015, 1, 30));
        self::assertNotEquals(self::d(2015, 1, 29), self::d(2015, 1, 30));
    }

    public function testInequalityOperator()
    {
        // arguments for the less-than assertion: 1) the value to be the greater one, 2) the value to be the lesser one
        self::assertLessThan(self::d(2016, 2, 28), self::d(2016, 2, 27));
        self::assertLessThan(self::d(2016, 2, 28), self::d(-1, 2, 27));
        self::assertLessThan(self::d(-12345, 2, 28), self::d(-12345, 2, 27));
        self::assertLessThan(Date::infinity(), self::d(2016, 2, 27));
        self::assertLessThan(self::d(2016, 2, 27), Date::minusInfinity());
    }

    public function testComparable()
    {
        self::assertEquals(0, self::d(2015, 1, 30)->compareTo(self::d(2015, 1, 30)));
        self::assertLessThan(0, self::d(2015, 1, 30)->compareTo(self::d(2015, 1, 31)));
        self::assertLessThan(0, self::d(2014, 1, 30)->compareTo(self::d(2015, 1, 30)));
        self::assertGreaterThan(0, self::d(2015, 1, 31)->compareTo(self::d(2015, 1, 30)));
        self::assertGreaterThan(0, self::d(2015, 1, 30)->compareTo(self::d(2014, 1, 30)));
    }

    public function testFromISOString()
    {
        self::assertEquals(self::d(2016, 2, 27), Date::fromISOString('2016-02-27'));
        self::assertEquals(self::d(-2016, 2, 27), Date::fromISOString('-2015-02-27'));
        self::assertEquals(self::d(12345, 6, 7), Date::fromISOString('12345-06-07'));
        self::assertEquals(self::d(-1, 2, 27), Date::fromISOString('0000-02-27'));
        self::assertEquals(self::d(-2, 2, 27), Date::fromISOString('-0001-02-27'));
    }

    public function testFromUnixTimestamp()
    {
        self::assertEquals(self::d(2016, 2, 27), Date::fromUnixTimestamp(gmmktime(0, 0, 0, 2, 27, 2016)));
        self::assertEquals(self::d(2016, 2, 27), Date::fromUnixTimestamp(gmmktime(23, 59, 59, 2, 27, 2016)));
    }

    public function testFromDateTime()
    {
        $utc = new \DateTimeZone('UTC');

        self::assertEquals(self::d(2016, 2, 27), Date::fromDateTime(new \DateTime('2016-02-27', $utc)));
        self::assertEquals(self::d(2016, 2, 27), Date::fromDateTime(new \DateTime('2016-02-27 15:48:12.1234', $utc)));

        self::assertEquals(self::d(2016, 2, 27), Date::fromDateTime(new \DateTimeImmutable('2016-02-27', $utc)));
        self::assertEquals(
            self::d(2016, 2, 27),
            Date::fromDateTime(new \DateTimeImmutable('2016-02-27 15:48:12.1234', $utc))
        );
    }

    public function testFromParts()
    {
        self::assertEquals(self::d(2016, 2, 27), Date::fromParts(2016, 2, 27));
        self::assertEquals(self::d(-2016, 2, 27), Date::fromParts(-2016, 2, 27));
        self::assertEquals(self::d(2016, 3, 1), Date::fromParts(2016, 2, 30));
        self::assertEquals(self::d(2016, 3, 5), Date::fromParts(2016, 2, 34));
        self::assertEquals(self::d(2017, 1, 31), Date::fromParts(2016, 14, 0));
        self::assertEquals(self::d(2016, 12, 26), Date::fromParts(2016, 13, -5));
        self::assertEquals(self::d(2014, 11, 1), Date::fromParts(2016, -13, 1));

        try {
            Date::fromParts(0, 1, 1);
            self::fail('Year number 0 shall be treated as invalid');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromPartsStrict()
    {
        self::assertEquals(self::d(2016, 2, 27), Date::fromPartsStrict(2016, 2, 27));
        self::assertEquals(self::d(-2016, 2, 27), Date::fromPartsStrict(-2016, 2, 27));

        try {
            Date::fromPartsStrict(2016, 2, 30);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 2, 34);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 14, 0);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, 13, -5);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(2016, -13, 1);
            self::fail();
        } catch (\OutOfRangeException $e) {
        }

        try {
            Date::fromPartsStrict(0, 1, 1);
            self::fail('Year number 0 shall be treated as invalid');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testAddParts()
    {
        self::assertEquals(self::d(2016, 2, 27), self::d(2016, 2, 25)->addParts(0, 0, 2));
        self::assertEquals(self::d(2016, 3, 2), self::d(2016, 2, 25)->addParts(0, 0, 6));
        self::assertEquals(self::d(2012, 2, 29), self::d(2015, 1, 25)->addParts(-3, 1, 4));

        self::assertEquals(self::d(2015, 7, 1), self::d(2015, 5, 31)->addParts(0, 1, 0));
        self::assertEquals(self::d(2015, 7, 2), self::d(2015, 5, 31)->addParts(0, 1, 1));
        self::assertEquals(self::d(2015, 8, 1), self::d(2015, 5, 31)->addParts(0, 2, 1));
        self::assertEquals(self::d(2015, 3, 29), self::d(2015, 2, 28)->addParts(0, 1, 1));
        self::assertEquals(self::d(2016, 2, 29), self::d(2015, 2, 28)->addParts(1, 0, 1));
        self::assertEquals(self::d(2017, 3, 1), self::d(2016, 2, 28)->addParts(1, 0, 1));

        self::assertEquals(Date::infinity(), Date::infinity()->addParts(1, 2, 3));
        self::assertEquals(Date::minusInfinity(), Date::minusInfinity()->addParts(1, 2, 3));
    }

    public function testToParts()
    {
        self::assertSame([2016, 1, 30], self::d(2016, 1, 30)->toParts());
        self::assertSame([-1, 1, 30], self::d(-1, 1, 30)->toParts());
        self::assertSame([-2016, 1, 30], self::d(-2016, 1, 30)->toParts());
        self::assertSame([12345, 1, 30], self::d(12345, 1, 30)->toParts());
        self::assertNull(Date::infinity()->toParts());
        self::assertNull(Date::minusInfinity()->toParts());
    }

    public function testToISOString()
    {
        self::assertSame('2016-01-30', self::d(2016, 1, 30)->toISOString());
        self::assertSame('0000-01-30', self::d(-1, 1, 30)->toISOString());
        self::assertSame('-2015-01-30', self::d(-2016, 1, 30)->toISOString());
        self::assertSame('12345-01-30', self::d(12345, 1, 30)->toISOString());
        self::assertNull(Date::infinity()->toISOString());
        self::assertNull(Date::minusInfinity()->toISOString());
    }

    public function testToUnixTimestamp()
    {
        self::assertSame(gmmktime(0, 0, 0, 1, 30, 2016), self::d(2016, 1, 30)->toUnixTimestamp());
        self::assertSame(
            gmmktime(0, 0, 0, 1, 30, 2016),
            Date::fromUnixTimestamp(gmmktime(12, 55, 0, 1, 30, 2016))->toUnixTimestamp()
        );
        self::assertNull(Date::infinity()->toUnixTimestamp());
        self::assertNull(Date::minusInfinity()->toUnixTimestamp());
    }
}
