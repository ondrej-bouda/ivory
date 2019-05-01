<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedOperationException;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class FixedBitStringTest extends TestCase
{
    public function testEquals()
    {
        self::assertTrue(FixedBitString::fromString('00101')->equals(FixedBitString::fromString('00101')));
        self::assertTrue(FixedBitString::fromString('1')->equals(FixedBitString::fromString('1')));
        self::assertTrue(FixedBitString::fromString('00101', 5)->equals(FixedBitString::fromString('00101', 5)));
        self::assertTrue(FixedBitString::fromString('001010')->equals(FixedBitString::fromString('00101', 6)));

        self::assertFalse(FixedBitString::fromString('101')->equals(FixedBitString::fromString('1010')));
        self::assertFalse(FixedBitString::fromString('1')->equals(FixedBitString::fromString('0')));
        self::assertFalse(FixedBitString::fromString('00101', 5)->equals(FixedBitString::fromString('00101', 6)));
        self::assertFalse(FixedBitString::fromString('000101')->equals(FixedBitString::fromString('00101', 6)));
    }

    public function testFromString()
    {
        self::assertTrue(FixedBitString::fromString('10011000011011001')->equals(
            FixedBitString::fromString('10011000011011001')
        ));

        self::assertTrue(FixedBitString::fromString('')->equals(FixedBitString::fromString('')));
        self::assertTrue(FixedBitString::fromString('001')->equals(FixedBitString::fromString('001')));
        self::assertTrue(FixedBitString::fromString('001')->equals(FixedBitString::fromString('001', 3)));
        self::assertTrue(FixedBitString::fromString('0010')->equals(FixedBitString::fromString('001', 4)));
        self::assertTrue(FixedBitString::fromString('1101')->equals(FixedBitString::fromString('1101')));
        self::assertTrue(FixedBitString::fromString('11010')->equals(FixedBitString::fromString('1101', 5)));

        try {
            FixedBitString::fromString('10011', 0);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            FixedBitString::fromString('10011', -4);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            FixedBitString::fromString('10311');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            self::fail('a warning is expected due to truncation');
        } catch (Warning $e) {
        }
        $fbs = @FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        self::assertTrue(FixedBitString::fromString('1010')->equals($fbs));
    }

    public function testToString()
    {
        self::assertSame('', FixedBitString::fromString('')->toString());
        self::assertSame('1010', FixedBitString::fromString('101', 4)->toString());
        self::assertSame('1010010110110100111010101011010111001101011010',
            FixedBitString::fromString('1010010110110100111010101011010111001101011010')->toString()
        );

        self::assertSame('1010', (string)FixedBitString::fromString('101', 4));

        try {
            FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            self::fail('a warning is expected due to truncation');
        } catch (Warning $e) {
        }
        $fbs = @FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        self::assertSame('1010', $fbs->toString());
    }

    public function testFromInt()
    {
        self::assertTrue(FixedBitString::fromString('101')->equals(FixedBitString::fromInt(5, 3)));
        self::assertTrue(FixedBitString::fromString('00101')->equals(FixedBitString::fromInt(5, 5)));
        self::assertTrue(FixedBitString::fromString('01')->equals(FixedBitString::fromInt(5, 2)));
        self::assertTrue(FixedBitString::fromString('0')->equals(FixedBitString::fromInt(0, 1)));
        self::assertTrue(FixedBitString::fromString('0000')->equals(FixedBitString::fromInt(0, 4)));
        self::assertTrue(FixedBitString::fromString('1111010110')->equals(FixedBitString::fromInt(-42, 10)));
        self::assertTrue(FixedBitString::fromString('1010110')->equals(FixedBitString::fromInt(-42, 7)));
        self::assertTrue(FixedBitString::fromString('010110')->equals(FixedBitString::fromInt(-42, 6)));

        self::assertTrue(FixedBitString::fromString('000000011000011011010001011100101')
            ->equals(FixedBitString::fromInt(51225317, 33))
        );
        self::assertTrue(FixedBitString::fromString('111111100111100100101110100011011')
            ->equals(FixedBitString::fromInt(-51225317, 33))
        );

        try {
            FixedBitString::fromInt(10011, 0);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            FixedBitString::fromInt(10011, -4);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testToInt()
    {
        self::assertSame(13, FixedBitString::fromString('1101')->toInt());
        self::assertSame(208, FixedBitString::fromString('1101', 8)->toInt());
        self::assertSame(13, FixedBitString::fromString('0001101')->toInt());
        self::assertSame(0, FixedBitString::fromString('0')->toInt());
        self::assertSame(2147483647, FixedBitString::fromString('1111111111111111111111111111111')->toInt());
        if (PHP_INT_SIZE == 4) {
            self::assertSame(5, FixedBitString::fromString('10000000000000000000000000000101')->toInt());
            self::assertSame(5, FixedBitString::fromString('110000000000000000000000000000101')->toInt());
            self::assertSame(
                1,
                FixedBitString::fromString('1000000000000000000000000000000000000000000000000000000000000001')->toInt()
            );
            self::assertSame(
                0,
                FixedBitString::fromString(
                    '110011000000000000000000000000000000000000000000000000000000000000000'
                )->toInt()
            );
        } elseif (PHP_INT_SIZE == 8) {
            self::assertSame(2147483653, FixedBitString::fromString('10000000000000000000000000000101')->toInt());
            self::assertSame(6442450949, FixedBitString::fromString('110000000000000000000000000000101')->toInt());
            self::assertSame(
                1,
                FixedBitString::fromString('1000000000000000000000000000000000000000000000000000000000000001')->toInt()
            );
            self::assertSame(
                0,
                FixedBitString::fromString(
                    '110011000000000000000000000000000000000000000000000000000000000000000'
                )->toInt()
            );
        }
    }

    public function testGetLength()
    {
        self::assertSame(0, FixedBitString::fromString('')->getLength());
        self::assertSame(1, FixedBitString::fromString('0')->getLength());
        self::assertSame(5, FixedBitString::fromString('10010')->getLength());
        self::assertSame(3, FixedBitString::fromString('110')->getLength());
        self::assertSame(5, FixedBitString::fromString('10010', 5)->getLength());
        self::assertSame(5, FixedBitString::fromString('110', 5)->getLength());
        self::assertSame(25, FixedBitString::fromString('1111011000100111000110010', 25)->getLength());
        self::assertSame(25, FixedBitString::fromString('111101100010011100011001', 25)->getLength());
        self::assertSame(1025, FixedBitString::fromString('1', 1025)->getLength());
    }

    public function testZero()
    {
        self::assertFalse(FixedBitString::fromString('10010')->isZero());
        self::assertTrue(FixedBitString::fromString('0')->isZero());
        self::assertTrue(FixedBitString::fromString('00000')->isZero());
        self::assertTrue(FixedBitString::fromString('00000', 7)->isZero());
        self::assertFalse(FixedBitString::fromString('1111011000100111000110010')->isZero());

        self::assertTrue(FixedBitString::fromString('10010')->isNonZero());
        self::assertFalse(FixedBitString::fromString('0')->isNonZero());
        self::assertFalse(FixedBitString::fromString('00000')->isNonZero());
        self::assertFalse(FixedBitString::fromString('00000', 7)->isNonZero());
        self::assertTrue(FixedBitString::fromString('1111011000100111000110010')->isNonZero());
    }

    public function testBitEquals()
    {
        self::assertTrue(FixedBitString::fromString('')->bitEquals(FixedBitString::fromString('')));
        self::assertTrue(FixedBitString::fromString('11001')->bitEquals(FixedBitString::fromString('11001')));
        self::assertTrue(FixedBitString::fromString('11001', 8)->bitEquals(FixedBitString::fromString('11001000')));
        self::assertTrue(FixedBitString::fromString('101')->bitEquals(FixedBitString::fromString('101')));

        self::assertFalse(FixedBitString::fromString('11001', 8)->bitEquals(FixedBitString::fromString('11001')));
        self::assertFalse(FixedBitString::fromString('00011001')->bitEquals(FixedBitString::fromString('11001')));
        self::assertFalse(FixedBitString::fromString('11001', 14)->bitEquals(FixedBitString::fromString('11001', 12)));
        self::assertFalse(FixedBitString::fromString('10010')->bitEquals(FixedBitString::fromString('10')));
        self::assertFalse(FixedBitString::fromString('10101')->bitEquals(FixedBitString::fromString('00011001')));

        self::assertTrue(
            FixedBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                FixedBitString::fromString('1111011000100111000110010')
            )
        );

        self::assertFalse(
            FixedBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                FixedBitString::fromString('1111011000110111000110010')
            )
        );
    }

    public function testIntersects()
    {
        self::assertTrue(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('110')));
        self::assertTrue(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('11010')));
        self::assertTrue(FixedBitString::fromString('110')->intersects(FixedBitString::fromString('11010')));
        self::assertTrue(FixedBitString::fromString('1')->intersects(FixedBitString::fromString('1101')));
        self::assertTrue(FixedBitString::fromString('1', 3)->intersects(FixedBitString::fromString('1101')));

        self::assertFalse(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('00100')));
        self::assertFalse(FixedBitString::fromString('001', 4)->intersects(FixedBitString::fromString('1100')));
    }

    public function testConcat()
    {
        self::assertTrue(VarBitString::fromString('10001011')->equals(
            FixedBitString::fromString('10001')->concat(FixedBitString::fromString('011'))
        ));

        self::assertTrue(VarBitString::fromString('1000100011')->equals(
            FixedBitString::fromString('10001', 7)->concat(FixedBitString::fromString('011'))
        ));

        self::assertTrue(VarBitString::fromString('1000101100')->equals(
            FixedBitString::fromString('10001')->concat(FixedBitString::fromString('011', 5))
        ));

        $exp = VarBitString::fromString('100101');
        $str = FixedBitString::fromString('100101');
        self::assertTrue($exp->equals($str->concat(FixedBitString::fromString(''))));
        self::assertTrue($exp->equals(FixedBitString::fromString('')->concat($str)));
    }

    public function testBitAnd()
    {
        self::assertTrue(FixedBitString::fromString('')->equals(
            FixedBitString::fromString('')->bitAnd(
                FixedBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1000000')->equals(
            FixedBitString::fromString('1100101')->bitAnd(
                FixedBitString::fromString('101', 7)
            )
        ));

        self::assertTrue(FixedBitString::fromString('0')->equals(
            FixedBitString::fromString('0')->bitAnd(
                FixedBitString::fromString('1')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1001000000100011000100010')->equals(
            FixedBitString::fromString('1111011000100111000110010')->bitAnd(
                FixedBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            FixedBitString::fromString('10010')->bitAnd(FixedBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitOr()
    {
        self::assertTrue(FixedBitString::fromString('')->equals(
            FixedBitString::fromString('')->bitOr(
                FixedBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1110101')->equals(
            FixedBitString::fromString('1100101')->bitOr(
                FixedBitString::fromString('101', 7)
            )
        ));

        self::assertTrue(FixedBitString::fromString('1')->equals(
            FixedBitString::fromString('0')->bitOr(
                FixedBitString::fromString('1')
            )
        ));

        self::assertTrue(FixedBitString::fromString('1111111100101111000110011')->equals(
            FixedBitString::fromString('1111011000100111000110010')->bitOr(
                FixedBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            FixedBitString::fromString('10010')->bitOr(FixedBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitXor()
    {
        self::assertTrue(FixedBitString::fromString('')->equals(
            FixedBitString::fromString('')->bitXor(
                FixedBitString::fromString('')
            )
        ));

        self::assertTrue(FixedBitString::fromString('0110101')->equals(
            FixedBitString::fromString('1100101')->bitXor(
                FixedBitString::fromString('101', 7)
            )
        ));

        self::assertTrue(FixedBitString::fromString('1')->equals(
            FixedBitString::fromString('0')->bitXor(
                FixedBitString::fromString('1')
            )
        ));

        self::assertTrue(FixedBitString::fromString('0110111100001100000010001')->equals(
            FixedBitString::fromString('1111011000100111000110010')->bitXor(
                FixedBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            FixedBitString::fromString('10010')->bitXor(FixedBitString::fromString('101'));
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitNot()
    {
        self::assertTrue(FixedBitString::fromString('110010110')
            ->equals(FixedBitString::fromString('001101001')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('0')
            ->equals(FixedBitString::fromString('1')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('')
            ->equals(FixedBitString::fromString('')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('111111')
            ->equals(FixedBitString::fromString('000', 6)->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('0010')
            ->equals(FixedBitString::fromString('1101')->bitNot())
        );
        self::assertTrue(FixedBitString::fromString('00001001110110001110011011')
            ->equals(FixedBitString::fromString('1111011000100111000110010', 26)->bitNot())
        );
    }

    public function testBitShiftLeft()
    {
        self::assertTrue(FixedBitString::fromString('0')
            ->equals(FixedBitString::fromString('0')->bitShiftLeft(4))
        );
        self::assertTrue(FixedBitString::fromString('000100')
            ->equals(FixedBitString::fromString('110001')->bitShiftLeft(2))
        );
        self::assertTrue(FixedBitString::fromString('0100000000')
            ->equals(FixedBitString::fromString('11001', 10)->bitShiftLeft(3))
        );
        self::assertTrue(FixedBitString::fromString('1100010011100011001000000')
            ->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(5))
        );
        self::assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(0))
        );
        self::assertTrue(FixedBitString::fromString('001101')
            ->equals(FixedBitString::fromString('110110')->bitShiftLeft(-2))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(FixedBitString::fromString('110110')->bitShiftLeft(123456789))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(FixedBitString::fromString('110110')->bitShiftLeft(-123456789))
        );
    }

    public function testBitShiftRight()
    {
        self::assertTrue(FixedBitString::fromString('00000')
            ->equals(FixedBitString::fromString('11001')->bitShiftRight(6))
        );
        self::assertTrue(FixedBitString::fromString('00000')
            ->equals(FixedBitString::fromString('11001')->bitShiftRight(5))
        );
        self::assertTrue(FixedBitString::fromString('00001')
            ->equals(FixedBitString::fromString('11001')->bitShiftRight(4))
        );
        self::assertTrue(FixedBitString::fromString('001101')
            ->equals(FixedBitString::fromString('110101')->bitShiftRight(2))
        );
        self::assertTrue(FixedBitString::fromString('0000011110110001001110001')
            ->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(5))
        );
        self::assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(0))
        );
        self::assertTrue(FixedBitString::fromString('')
            ->equals(FixedBitString::fromString('')->bitShiftRight(0))
        );
        self::assertTrue(FixedBitString::fromString('')
            ->equals(FixedBitString::fromString('')->bitShiftRight(4))
        );
        self::assertTrue(FixedBitString::fromString('101100')
            ->equals(FixedBitString::fromString('110110')->bitShiftRight(-1))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(FixedBitString::fromString('101100')->bitShiftRight(-123456789))
        );
        self::assertTrue(FixedBitString::fromString('000000')
            ->equals(FixedBitString::fromString('101100')->bitShiftRight(123456789))
        );
    }

    public function testBitRotateLeft()
    {
        self::assertTrue(FixedBitString::fromString('')
            ->equals(FixedBitString::fromString('')->bitRotateLeft(1))
        );
        self::assertTrue(FixedBitString::fromString('100110')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(0))
        );
        self::assertTrue(FixedBitString::fromString('011010')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(2))
        );
        self::assertTrue(FixedBitString::fromString('011010')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(8))
        );
        self::assertTrue(FixedBitString::fromString('010011')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(-1))
        );
        self::assertTrue(FixedBitString::fromString('001101')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(1234567))
        );
        self::assertTrue(FixedBitString::fromString('010011')
            ->equals(FixedBitString::fromString('100110')->bitRotateLeft(-1234567))
        );
    }

    public function testBitRotateRight()
    {
        self::assertTrue(FixedBitString::fromString('')
            ->equals(FixedBitString::fromString('')->bitRotateRight(1))
        );
        self::assertTrue(FixedBitString::fromString('100110')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(0))
        );
        self::assertTrue(FixedBitString::fromString('101001')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(2))
        );
        self::assertTrue(FixedBitString::fromString('101001')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(8))
        );
        self::assertTrue(FixedBitString::fromString('001101')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(-1))
        );
        self::assertTrue(FixedBitString::fromString('010011')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(1234567))
        );
        self::assertTrue(FixedBitString::fromString('001101')
            ->equals(FixedBitString::fromString('100110')->bitRotateRight(-1234567))
        );
    }

    public function testSubstring()
    {
        self::assertTrue(FixedBitString::fromString('101001')->equals(
            FixedBitString::fromString('1101001')->substring(2)
        ));

        self::assertTrue(FixedBitString::fromString('1010')->equals(
            FixedBitString::fromString('1101001')->substring(2, 4)
        ));

        try {
            FixedBitString::fromString('1101001')->substring(2, -1);
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        self::assertTrue(FixedBitString::fromString('')->equals(
            FixedBitString::fromString('1101001')->substring(2, 0)
        ));

        self::assertTrue(FixedBitString::fromString('1')->equals(
            FixedBitString::fromString('1101001')->substring(-2, 4)
        ));
    }

    public function testArrayAccess()
    {
        $small = FixedBitString::fromString('1100101');
        $big = FixedBitString::fromString('1111011000100111000110010');
        $padded = FixedBitString::fromString('001100101', 10);

        self::assertSame(1, $small[0]);
        self::assertSame(1, $small[1]);
        self::assertSame(0, $small[2]);
        self::assertSame(1, $small[6]);
        self::assertSame(1, $small[-1]);
        self::assertSame(0, $small[-2]);
        self::assertSame(1, $small[-6]);
        self::assertSame(1, $small[-7]);
        self::assertSame(0, $big[24]);
        self::assertSame(1, $padded[8]);
        self::assertSame(0, $padded[9]);
        self::assertNull($small[7]);
        self::assertNull($small[8]);
        self::assertNull($small[-8]);
        self::assertNull($small[-9]);
        self::assertNull($big[25]);
        self::assertNull($padded[10]);

        self::assertTrue(isset($small[0]));
        self::assertTrue(isset($small[1]));
        self::assertTrue(isset($small[2]));
        self::assertTrue(isset($small[6]));
        self::assertTrue(isset($small[-1]));
        self::assertTrue(isset($small[-2]));
        self::assertTrue(isset($small[-6]));
        self::assertTrue(isset($small[-7]));
        self::assertTrue(isset($big[24]));
        self::assertTrue(isset($padded[8]));
        self::assertTrue(isset($padded[9]));
        self::assertFalse(isset($small[7]));
        self::assertFalse(isset($small[8]));
        self::assertFalse(isset($small[-8]));
        self::assertFalse(isset($small[-9]));
        self::assertFalse(isset($big[25]));
        self::assertFalse(isset($padded[10]));

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
