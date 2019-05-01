<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use Ivory\Exception\UnsupportedException;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function testFromString()
    {
        $q = Quantity::fromString(' 1   unit ');
        self::assertSame(1, $q->getValue());
        self::assertSame('unit', $q->getUnit());

        $q = Quantity::fromString('1.0kB');
        self::assertSame(1.0, $q->getValue());
        self::assertSame('kB', $q->getUnit());

        $q = Quantity::fromString('5apple');
        self::assertSame(5, $q->getValue());
        self::assertSame('apple', $q->getUnit());

        $q = Quantity::fromString('$3,999.99');
        self::assertSame(3999.99, $q->getValue());
        self::assertSame('$', $q->getUnit());

        $q = Quantity::fromString('$   3 , 9 99.99');
        self::assertSame(3999.99, $q->getValue());
        self::assertSame('$', $q->getUnit());

        $q = Quantity::fromString('123');
        self::assertSame(123, $q->getValue());
        self::assertSame(null, $q->getUnit());

        try {
            Quantity::fromString('unit');
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromValue()
    {
        $q = Quantity::fromValue(1, 'unit');
        self::assertSame(1, $q->getValue());
        self::assertSame('unit', $q->getUnit());

        $q = Quantity::fromValue(-123.0);
        self::assertSame(-123.0, $q->getValue());
        self::assertSame(null, $q->getUnit());

        $q = Quantity::fromValue(-123.0, '');
        self::assertSame(-123.0, $q->getValue());
        self::assertSame(null, $q->getUnit());

        $q = Quantity::fromValue('5', 'apple');
        self::assertSame(5, $q->getValue());
        self::assertSame('apple', $q->getUnit());

        try {
            Quantity::fromValue('5 apples', 'unit');
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Quantity::fromValue(null, 'unit');
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testEquals()
    {
        self::assertTrue(Quantity::fromValue(5, Quantity::MINUTE)->equals(Quantity::fromValue(300, Quantity::SECOND)));
        self::assertTrue(Quantity::fromString('1.5kB')->equals('1536B'));
        self::assertTrue(Quantity::fromValue(15)->equals(Quantity::fromString('15')));
        self::assertTrue(Quantity::fromValue(15, 'abcd')->equals(Quantity::fromString('15 abcd')));

        self::assertFalse(Quantity::fromValue(15)->equals(Quantity::fromValue(3, Quantity::MINUTE)));
        self::assertFalse(Quantity::fromValue(15, Quantity::BYTE)->equals(Quantity::fromValue(15, Quantity::MINUTE)));
        self::assertFalse(Quantity::fromValue(15, 'abcd')->equals(Quantity::fromString('15 def')));
    }

    public function testConvert()
    {
        $q = Quantity::fromValue(12, Quantity::MINUTE);
        $c = $q->convert(Quantity::MILLISECOND);
        self::assertSame(12 * 60 * 1000, $c->getValue());
        self::assertSame(Quantity::MILLISECOND, $c->getUnit());

        $q = Quantity::fromValue(15302.5, Quantity::KILOBYTE);
        $c = $q->convert(Quantity::BYTE);
        self::assertSame(15669760, $c->getValue());
        self::assertSame(Quantity::BYTE, $c->getUnit());

        $q = Quantity::fromValue(15302.5, Quantity::KILOBYTE);
        $c = $q->convert(Quantity::GIGABYTE);
        self::assertEquals(0.014593601226806640625, $c->getValue(), '', 1e-20);
        self::assertSame(Quantity::GIGABYTE, $c->getUnit());

        try {
            Quantity::fromValue(42)->convert(Quantity::MILLISECOND);
            self::fail('\Ivory\UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(42, Quantity::MILLISECOND)->convert('');
            self::fail('\Ivory\UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(42, Quantity::BYTE)->convert(Quantity::MILLISECOND);
            self::fail('\Ivory\UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(5, Quantity::MINUTE)->convert(Quantity::TERABYTE);
            self::fail('\Ivory\UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(123, 'abc')->convert(Quantity::BYTE);
            self::fail('\Ivory\UnsupportedException expected');
        } catch (UnsupportedException $e) {
        }

        try {
            Quantity::fromValue(123, Quantity::BYTE)->convert('abc');
            self::fail('\Ivory\UnsupportedException expected');
        } catch (UnsupportedException $e) {
        }
    }

    public function testToString()
    {
        self::assertSame('1 kB', Quantity::fromValue(1, Quantity::KILOBYTE)->toString());
        self::assertSame('-0.123 s', Quantity::fromValue(-.123, Quantity::SECOND)->toString());
        self::assertSame('0', Quantity::fromValue(0)->toString());
    }
}
