<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests of operations combining objects of different BitString subclasses as operands.
 */
class BitStringTest extends TestCase
{
    public function testEquals()
    {
        self::assertFalse(VarBitString::fromString('110')
            ->equals(FixedBitString::fromString('10110'))
        );
        self::assertFalse(FixedBitString::fromString('10110')
            ->equals(VarBitString::fromString('110'))
        );

        self::assertFalse(VarBitString::fromString('10110')
            ->equals(FixedBitString::fromString('10110'))
        );
        self::assertFalse(FixedBitString::fromString('10110')
            ->equals(VarBitString::fromString('10110'))
        );

        self::assertFalse(VarBitString::fromString('10110', 5)
            ->equals(FixedBitString::fromString('10110'))
        );
        self::assertFalse(FixedBitString::fromString('10110')
            ->equals(VarBitString::fromString('10110', 5))
        );
    }

    public function testBitEquals()
    {
        self::assertFalse(VarBitString::fromString('110')
            ->bitEquals(FixedBitString::fromString('10110'))
        );
        self::assertFalse(FixedBitString::fromString('10110')
            ->bitEquals(VarBitString::fromString('110'))
        );

        self::assertTrue(VarBitString::fromString('10110')
            ->bitEquals(FixedBitString::fromString('10110'))
        );
        self::assertTrue(FixedBitString::fromString('10110')
            ->bitEquals(VarBitString::fromString('10110'))
        );

        self::assertTrue(VarBitString::fromString('10110', 5)
            ->bitEquals(FixedBitString::fromString('10110'))
        );
        self::assertTrue(FixedBitString::fromString('10110')
            ->bitEquals(VarBitString::fromString('10110', 5))
        );

        self::assertTrue(VarBitString::fromString('10110', 6)
            ->bitEquals(FixedBitString::fromString('10110'))
        );
        self::assertTrue(FixedBitString::fromString('10110')
            ->bitEquals(VarBitString::fromString('10110', 6))
        );
    }

    public function testIntersects()
    {
        self::assertTrue(FixedBitString::fromString('11010')->intersects(VarBitString::fromString('101')));
        self::assertTrue(VarBitString::fromString('101')->intersects(FixedBitString::fromString('11010')));

        self::assertTrue(FixedBitString::fromString('101')->intersects(VarBitString::fromString('11010')));
        self::assertTrue(VarBitString::fromString('11010')->intersects(FixedBitString::fromString('101')));

        self::assertFalse(FixedBitString::fromString('11010')->intersects(VarBitString::fromString('001')));
        self::assertFalse(VarBitString::fromString('001')->intersects(FixedBitString::fromString('11010')));

        self::assertFalse(FixedBitString::fromString('001')->intersects(VarBitString::fromString('11010')));
        self::assertFalse(VarBitString::fromString('11010')->intersects(FixedBitString::fromString('001')));
    }

    public function testConcat()
    {
        self::assertTrue(VarBitString::fromString('10001011')->equals(
            FixedBitString::fromString('10001')->concat(VarBitString::fromString('011'))
        ));
        self::assertTrue(VarBitString::fromString('01110001')->equals(
            VarBitString::fromString('011')->concat(FixedBitString::fromString('10001'))
        ));

        self::assertTrue(VarBitString::fromString('100010011')->equals(
            FixedBitString::fromString('10001', 6)->concat(VarBitString::fromString('011', 6))
        ));
        self::assertTrue(VarBitString::fromString('011100010')->equals(
            VarBitString::fromString('011', 6)->concat(FixedBitString::fromString('10001', 6))
        ));
    }

    public function testBitAnd()
    {
        self::assertTrue(FixedBitString::fromString('1000000')->equals(
            FixedBitString::fromString('1100101')->bitAnd(
                VarBitString::fromString('1010000', 9)
            )
        ));
        self::assertTrue(FixedBitString::fromString('1000000')->equals(
            VarBitString::fromString('1010000', 9)->bitAnd(
                FixedBitString::fromString('1100101')
            )
        ));

        try {
            FixedBitString::fromString('1100101')->bitAnd(VarBitString::fromString('101', 7));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            VarBitString::fromString('101', 7)->bitAnd(FixedBitString::fromString('1100101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('101')->bitAnd(FixedBitString::fromString('10010'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            FixedBitString::fromString('10010')->bitAnd(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitOr()
    {
        self::assertTrue(FixedBitString::fromString('1110101')->equals(
            FixedBitString::fromString('1100101')->bitOr(
                VarBitString::fromString('1010000', 9)
            )
        ));
        self::assertTrue(FixedBitString::fromString('1110101')->equals(
            VarBitString::fromString('1010000', 9)->bitOr(
                FixedBitString::fromString('1100101')
            )
        ));

        try {
            FixedBitString::fromString('1100101')->bitOr(VarBitString::fromString('101', 7));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            VarBitString::fromString('101', 7)->bitOr(FixedBitString::fromString('1100101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('101')->bitOr(FixedBitString::fromString('10010'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            FixedBitString::fromString('10010')->bitOr(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitXor()
    {
        self::assertTrue(FixedBitString::fromString('0110101')->equals(
            FixedBitString::fromString('1100101')->bitXor(
                VarBitString::fromString('1010000', 9)
            )
        ));
        self::assertTrue(FixedBitString::fromString('0110101')->equals(
            VarBitString::fromString('1010000', 9)->bitXor(
                FixedBitString::fromString('1100101')
            )
        ));

        try {
            FixedBitString::fromString('1100101')->bitXor(VarBitString::fromString('101', 7));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            VarBitString::fromString('101', 7)->bitXor(FixedBitString::fromString('1100101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('101')->bitXor(FixedBitString::fromString('10010'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
        try {
            FixedBitString::fromString('10010')->bitXor(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }
}
