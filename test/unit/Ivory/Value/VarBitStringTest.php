<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedOperationException;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class VarBitStringTest extends TestCase
{
    public function testEquals()
    {
        self::assertTrue(VarBitString::fromString('00101')->equals(VarBitString::fromString('00101')));
        self::assertTrue(VarBitString::fromString('1')->equals(VarBitString::fromString('1')));
        self::assertTrue(VarBitString::fromString('00101', 5)->equals(VarBitString::fromString('00101', 5)));

        self::assertFalse(VarBitString::fromString('101')->equals(VarBitString::fromString('1010')));
        self::assertFalse(VarBitString::fromString('1')->equals(VarBitString::fromString('0')));
        self::assertFalse(VarBitString::fromString('00101', 5)->equals(VarBitString::fromString('00101', 6)));
    }

    public function testFromString()
    {
        self::assertTrue(VarBitString::fromString('10011000011011001')
            ->equals(VarBitString::fromString('10011000011011001'))
        );
        self::assertTrue(VarBitString::fromString('')->equals(VarBitString::fromString('')));
        self::assertTrue(VarBitString::fromString('001')->equals(VarBitString::fromString('001')));
        self::assertFalse(VarBitString::fromString('001', 3)->equals(VarBitString::fromString('001')));
        self::assertFalse(VarBitString::fromString('001', 4)->equals(VarBitString::fromString('001')));
        self::assertTrue(VarBitString::fromString('1101')->equals(VarBitString::fromString('1101')));

        try {
            VarBitString::fromString('10011', 0);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('10011', -4);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('10311');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            self::fail('a warning is expected due to truncation');
        } catch (Warning $e) {
        }
        $vbs = @VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        self::assertTrue(VarBitString::fromString('1010', 4)->equals($vbs));
    }

    public function testToString()
    {
        self::assertSame('', VarBitString::fromString('', 4)->toString());
        self::assertSame('101', VarBitString::fromString('101', 4)->toString());
        self::assertSame('1010010110110100111010101011010111001101011010',
            VarBitString::fromString('1010010110110100111010101011010111001101011010')->toString()
        );

        self::assertSame('101', (string)VarBitString::fromString('101', 4));

        try {
            VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            self::fail('a warning is expected due to truncation');
        } catch (Warning $e) {
        }
        $vbs = @VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        self::assertSame('1010', $vbs->toString());
    }

    public function testGetLength()
    {
        self::assertSame(0, VarBitString::fromString('')->getLength());
        self::assertSame(1, VarBitString::fromString('0')->getLength());
        self::assertSame(5, VarBitString::fromString('10010')->getLength());
        self::assertSame(3, VarBitString::fromString('110')->getLength());
        self::assertSame(5, VarBitString::fromString('10010', 5)->getLength());
        self::assertSame(3, VarBitString::fromString('110', 5)->getLength());
        self::assertSame(25, VarBitString::fromString('1111011000100111000110010', 25)->getLength());
        self::assertSame(24, VarBitString::fromString('111101100010011100011001', 25)->getLength());
        self::assertSame(1, VarBitString::fromString('1', 1025)->getLength());
    }

    public function testGetMaxLength()
    {
        self::assertNull(VarBitString::fromString('')->getMaxLength());
        self::assertNull(VarBitString::fromString('0')->getMaxLength());
        self::assertNull(VarBitString::fromString('10010')->getMaxLength());
        self::assertSame(5, VarBitString::fromString('10010', 5)->getMaxLength());
        self::assertSame(5, VarBitString::fromString('110', 5)->getMaxLength());
        self::assertSame(25, VarBitString::fromString('1111011000100111000110010', 25)->getMaxLength());
        self::assertSame(25, VarBitString::fromString('111101100010011100011001', 25)->getMaxLength());
        self::assertSame(1025, VarBitString::fromString('1', 1025)->getMaxLength());
    }

    public function testZero()
    {
        self::assertTrue(VarBitString::fromString('0')->isZero());
        self::assertTrue(VarBitString::fromString('00000')->isZero());
        self::assertTrue(VarBitString::fromString('00000', 7)->isZero());
        self::assertFalse(VarBitString::fromString('10010')->isZero());
        self::assertFalse(VarBitString::fromString('1111011000100111000110010')->isZero());

        self::assertFalse(VarBitString::fromString('0')->isNonZero());
        self::assertFalse(VarBitString::fromString('00000')->isNonZero());
        self::assertFalse(VarBitString::fromString('00000', 7)->isNonZero());
        self::assertTrue(VarBitString::fromString('10010')->isNonZero());
        self::assertTrue(VarBitString::fromString('1111011000100111000110010')->isNonZero());
    }

    public function testBitEquals()
    {
        self::assertTrue(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('11001')));
        self::assertTrue(VarBitString::fromString('11001', 8)->bitEquals(VarBitString::fromString('11001')));
        self::assertTrue(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('11001', 9)));
        self::assertTrue(VarBitString::fromString('11001', 14)->bitEquals(VarBitString::fromString('11001', 12)));

        self::assertFalse(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('00011001')));
        self::assertFalse(VarBitString::fromString('00011001')->bitEquals(VarBitString::fromString('11001')));
        self::assertFalse(VarBitString::fromString('00011001')->bitEquals(VarBitString::fromString('0011001')));
        self::assertFalse(VarBitString::fromString('10101')->bitEquals(VarBitString::fromString('00011001')));

        self::assertTrue(
            VarBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                VarBitString::fromString('1111011000100111000110010')
            )
        );

        self::assertFalse(
            VarBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                VarBitString::fromString('1111011000110111000110010')
            )
        );
    }

    public function testIntersects()
    {
        self::assertTrue(VarBitString::fromString('11010')->intersects(VarBitString::fromString('110')));
        self::assertTrue(VarBitString::fromString('11010')->intersects(VarBitString::fromString('11010')));
        self::assertTrue(VarBitString::fromString('110')->intersects(VarBitString::fromString('11010')));
        self::assertTrue(VarBitString::fromString('1')->intersects(VarBitString::fromString('1101')));
        self::assertTrue(VarBitString::fromString('1', 3)->intersects(VarBitString::fromString('1101')));

        self::assertFalse(VarBitString::fromString('11010')->intersects(VarBitString::fromString('00100')));
        self::assertFalse(VarBitString::fromString('1')->intersects(VarBitString::fromString('01')));
    }

    public function testConcat()
    {
        self::assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 7)->concat(
                VarBitString::fromString('011')
            )
        ));

        self::assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 7)->concat(
                VarBitString::fromString('011', 6)
            )
        ));

        self::assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 6)->concat(
                VarBitString::fromString('011', 6)
            )
        ));

        $str = VarBitString::fromString('100101');
        self::assertTrue($str->equals($str->concat(VarBitString::fromString(''))));
        self::assertTrue($str->equals(VarBitString::fromString('')->concat($str)));
    }

    public function testBitAnd()
    {
        self::assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 7)->bitAnd(
                VarBitString::fromString('01101')
            )
        ));

        self::assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 7)->bitAnd(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 6)->bitAnd(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitAnd(
                VarBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1001000000100011000100010')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitAnd(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitAnd(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitAnd(VarBitString::fromString('101', 5));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitOr()
    {
        self::assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 7)->bitOr(
                VarBitString::fromString('01101')
            )
        ));

        self::assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 7)->bitOr(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 6)->bitOr(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitOr(
                VarBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1111111100101111000110011')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitOr(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitOr(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitOr(VarBitString::fromString('101', 5));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitXor()
    {
        self::assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 7)->bitXor(
                VarBitString::fromString('01101')
            )
        ));

        self::assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 7)->bitXor(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 6)->bitXor(
                VarBitString::fromString('01101', 6)
            )
        ));

        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitXor(
                VarBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('0110111100001100000010001')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitXor(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitXor(VarBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitXor(VarBitString::fromString('101', 5));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitNot()
    {
        self::assertTrue(FixedBitString::fromString('110010110')
            ->equals(VarBitString::fromString('001101001')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('110010110')
            ->equals(VarBitString::fromString('001101001', 9)->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('0')
            ->equals(VarBitString::fromString('1')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('')
            ->equals(VarBitString::fromString('')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('111')
            ->equals(VarBitString::fromString('000', 6)->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('0000100111011000111001101')
            ->equals(VarBitString::fromString('1111011000100111000110010', 25)->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('0010')
            ->equals(VarBitString::fromString('1101')->bitNot())
        );
    }

    public function testBitShiftLeft()
    {
        self::assertTrue(FixedBitString::fromString('0')
            ->equals(VarBitString::fromString('0')->bitShiftLeft(4))
        );
        self::assertTrue(FixedBitString::fromString('10000')
            ->equals(VarBitString::fromString('11001')->bitShiftLeft(4))
        );
        self::assertTrue(FixedBitString::fromString('01000')
            ->equals(VarBitString::fromString('110001', 6)->bitShiftLeft(3))
        );
        self::assertTrue(FixedBitString::fromString('1100010011100011001000000')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(5))
        );
        self::assertTrue(FixedBitString::fromString('1100010011100011001000000')
            ->equals(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftLeft(5))
        );
        self::assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(0))
        );
        self::assertTrue(FixedBitString::fromString('011010')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(-1))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(123456789))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(-123456789))
        );
    }

    public function testBitShiftRight()
    {
        self::assertTrue(FixedBitString::fromString('00000')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(6))
        );
        self::assertTrue(FixedBitString::fromString('00000')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(5))
        );
        self::assertTrue(FixedBitString::fromString('00001')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(4))
        );
        self::assertTrue(FixedBitString::fromString('000110')
            ->equals(VarBitString::fromString('110101', 6)->bitShiftRight(3))
        );
        self::assertTrue(FixedBitString::fromString('0000011110110001001110001')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(5))
        );
        self::assertTrue(FixedBitString::fromString('0000011110110001001110001')
            ->equals(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftRight(5))
        );
        self::assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(0))
        );
        self::assertTrue(FixedBitString::fromString('101000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(-1))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(123456789))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(-123456789))
        );
    }

    public function testBitRotateLeft()
    {
        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitRotateLeft(1)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011')->bitRotateLeft(1)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(1)
        ));

        self::assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(0)
        ));

        self::assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(5)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(6)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(-1)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(-6)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(123456789)
        ));
    }

    public function testBitRotateRight()
    {
        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitRotateRight(1)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011')->bitRotateRight(1)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(1)
        ));

        self::assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(0)
        ));

        self::assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(5)
        ));

        self::assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(6)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(-1)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(-6)
        ));

        self::assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(123456789)
        ));
    }

    public function testSubstring()
    {
        self::assertTrue(FixedBitString::fromString('101001')->equals(
            VarBitString::fromString('1101001')->substring(2)
        ));

        self::assertTrue(FixedBitString::fromString('1010')->equals(
            VarBitString::fromString('1101001')->substring(2, 4)
        ));

        try {
            VarBitString::fromString('1101001')->substring(2, -1);
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        self::assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('1101001')->substring(2, 0)
        ));

        self::assertTrue(FixedBitString::fromString('1')->equals(
            VarBitString::fromString('1101001')->substring(-2, 4)
        ));

        self::assertTrue(FixedBitString::fromString('1010')->equals(
            VarBitString::fromString('1101001', 15)->substring(2, 4)
        ));
    }

    public function testArrayAccess()
    {
        $small = VarBitString::fromString('1100101');
        $big = VarBitString::fromString('1111011000100111000110010');
        $limited = VarBitString::fromString('001100101', 10);

        self::assertSame(1, $small[0]);
        self::assertSame(1, $small[1]);
        self::assertSame(0, $small[2]);
        self::assertSame(1, $small[6]);
        self::assertSame(1, $small[-1]);
        self::assertSame(0, $small[-2]);
        self::assertSame(1, $small[-6]);
        self::assertSame(1, $small[-7]);
        self::assertSame(0, $big[24]);
        self::assertSame(1, $limited[8]);
        self::assertNull($small[7]);
        self::assertNull($small[8]);
        self::assertNull($small[-8]);
        self::assertNull($small[-9]);
        self::assertNull($big[25]);
        self::assertNull($limited[9]);

        self::assertTrue(isset($small[0]));
        self::assertTrue(isset($small[1]));
        self::assertTrue(isset($small[2]));
        self::assertTrue(isset($small[6]));
        self::assertTrue(isset($small[-1]));
        self::assertTrue(isset($small[-2]));
        self::assertTrue(isset($small[-6]));
        self::assertTrue(isset($small[-7]));
        self::assertTrue(isset($big[24]));
        self::assertTrue(isset($limited[8]));
        self::assertFalse(isset($small[7]));
        self::assertFalse(isset($small[8]));
        self::assertFalse(isset($small[-8]));
        self::assertFalse(isset($small[-9]));
        self::assertFalse(isset($big[25]));
        self::assertFalse(isset($limited[9]));

        try {
            $small[2] = 0;
            self::fail('ImmutableException expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($small[2]);
            self::fail('ImmutableException expected');
        } catch (ImmutableException $e) {
        }
    }
}
