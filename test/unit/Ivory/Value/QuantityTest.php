<?php
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use Ivory\Exception\UnsupportedException;

class QuantityTest extends \PHPUnit_Framework_TestCase
{
    public function testFromString()
    {
        $q = Quantity::fromString(' 1   unit ');
        $this->assertSame(1, $q->getValue());
        $this->assertSame('unit', $q->getUnit());

        $q = Quantity::fromString('1.0kB');
        $this->assertSame(1.0, $q->getValue());
        $this->assertSame('kB', $q->getUnit());

        $q = Quantity::fromString('5apple');
        $this->assertSame(5, $q->getValue());
        $this->assertSame('apple', $q->getUnit());

        $q = Quantity::fromString('$3,999.99');
        $this->assertSame(3999.99, $q->getValue());
        $this->assertSame('$', $q->getUnit());

        $q = Quantity::fromString('$   3 , 9 99.99');
        $this->assertSame(3999.99, $q->getValue());
        $this->assertSame('$', $q->getUnit());

        $q = Quantity::fromString('123');
        $this->assertSame(123, $q->getValue());
        $this->assertSame(null, $q->getUnit());

        $q = Quantity::fromString(123);
        $this->assertSame(123, $q->getValue());
        $this->assertSame(null, $q->getUnit());

        try {
            Quantity::fromString('unit');
            $this->fail('\InvalidArgumentException expected');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromValue()
    {
        $q = Quantity::fromValue(1, 'unit');
        $this->assertSame(1, $q->getValue());
        $this->assertSame('unit', $q->getUnit());

        $q = Quantity::fromValue(-123.0);
        $this->assertSame(-123.0, $q->getValue());
        $this->assertSame(null, $q->getUnit());

        $q = Quantity::fromValue(-123.0, '');
        $this->assertSame(-123.0, $q->getValue());
        $this->assertSame(null, $q->getUnit());

        try {
            Quantity::fromValue('5 apples', 'unit');
            $this->fail('\InvalidArgumentException expected');
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            Quantity::fromValue(null, 'unit');
            $this->fail('\InvalidArgumentException expected');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testEquals()
    {
        $this->assertTrue(Quantity::fromValue(5, Quantity::MINUTE)->equals(Quantity::fromValue(300, Quantity::SECOND)));
        $this->assertTrue(Quantity::fromString('1.5kB')->equals('1536B'));
        $this->assertTrue(Quantity::fromValue(15)->equals(Quantity::fromString('15')));
        $this->assertTrue(Quantity::fromValue(15, 'abcd')->equals(Quantity::fromString('15 abcd')));

        $this->assertFalse(Quantity::fromValue(15)->equals(Quantity::fromValue(3, Quantity::MINUTE)));
        $this->assertFalse(Quantity::fromValue(15, Quantity::BYTE)->equals(Quantity::fromValue(15, Quantity::MINUTE)));
        $this->assertFalse(Quantity::fromValue(15, 'abcd')->equals(Quantity::fromString('15 def')));
    }

    public function testConvert()
    {
        $q = Quantity::fromValue(12, Quantity::MINUTE);
        $c = $q->convert(Quantity::MILLISECOND);
        $this->assertSame(12 * 60 * 1000, $c->getValue());
        $this->assertSame(Quantity::MILLISECOND, $c->getUnit());

        $q = Quantity::fromValue(15302.5, Quantity::KILOBYTE);
        $c = $q->convert(Quantity::BYTE);
        $this->assertSame(15669760, $c->getValue());
        $this->assertSame(Quantity::BYTE, $c->getUnit());

        $q = Quantity::fromValue(15302.5, Quantity::KILOBYTE);
        $c = $q->convert(Quantity::GIGABYTE);
        $this->assertEquals(0.014593601226806640625, $c->getValue(), null, 1e-20);
        $this->assertSame(Quantity::GIGABYTE, $c->getUnit());

        try {
            Quantity::fromValue(42)->convert(Quantity::MILLISECOND);
            $this->fail('\Ivory\UndefinedOperationException expected');
        }
        catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(42, Quantity::MILLISECOND)->convert('');
            $this->fail('\Ivory\UndefinedOperationException expected');
        }
        catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(42, Quantity::BYTE)->convert(Quantity::MILLISECOND);
            $this->fail('\Ivory\UndefinedOperationException expected');
        }
        catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(5, Quantity::MINUTE)->convert(Quantity::TERABYTE);
            $this->fail('\Ivory\UndefinedOperationException expected');
        }
        catch (UndefinedOperationException $e) {
        }

        try {
            Quantity::fromValue(123, 'abc')->convert(Quantity::BYTE);
            $this->fail('\Ivory\UnsupportedException expected');
        }
        catch (UnsupportedException $e) {
        }

        try {
            Quantity::fromValue(123, Quantity::BYTE)->convert('abc');
            $this->fail('\Ivory\UnsupportedException expected');
        }
        catch (UnsupportedException $e) {
        }
    }

    public function testToString()
    {
        $this->assertSame('1 kB', Quantity::fromValue(1, Quantity::KILOBYTE)->toString());
        $this->assertSame('-0.123 s', Quantity::fromValue(-.123, Quantity::SECOND)->toString());
        $this->assertSame('0', Quantity::fromValue(0)->toString());
    }
}
