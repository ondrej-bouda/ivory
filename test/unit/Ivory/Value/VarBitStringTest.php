<?php
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedOperationException;

class VarBitStringTest extends \PHPUnit\Framework\TestCase
{
    public function testEquals()
    {
        $this->assertTrue(VarBitString::fromString('00101')->equals(VarBitString::fromString('00101')));
        $this->assertTrue(VarBitString::fromString('1')->equals(VarBitString::fromString('1')));
        $this->assertTrue(VarBitString::fromString('00101', 5)->equals(VarBitString::fromString('00101', 5)));

        $this->assertFalse(VarBitString::fromString('101')->equals(VarBitString::fromString('1010')));
        $this->assertFalse(VarBitString::fromString('1')->equals(VarBitString::fromString('0')));
        $this->assertFalse(VarBitString::fromString('00101', 5)->equals(VarBitString::fromString('00101', 6)));
    }

    public function testFromString()
    {
        $this->assertTrue(VarBitString::fromString('10011000011011001')
            ->equals(VarBitString::fromString('10011000011011001'))
        );
        $this->assertTrue(VarBitString::fromString('')->equals(VarBitString::fromString('')));
        $this->assertTrue(VarBitString::fromString('001')->equals(VarBitString::fromString('001')));
        $this->assertFalse(VarBitString::fromString('001', 3)->equals(VarBitString::fromString('001')));
        $this->assertFalse(VarBitString::fromString('001', 4)->equals(VarBitString::fromString('001')));
        $this->assertTrue(VarBitString::fromString(1101)->equals(VarBitString::fromString('1101')));

        try {
            VarBitString::fromString('10011', 0);
            $this->fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('10011', -4);
            $this->fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('10311');
            $this->fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString(10311);
            $this->fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            $this->fail('a warning is expected due to truncation');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
        }
        $vbs = @VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        $this->assertTrue(VarBitString::fromString('1010', 4)->equals($vbs));
    }

    public function testToString()
    {
        $this->assertSame('', VarBitString::fromString('', 4)->toString());
        $this->assertSame('101', VarBitString::fromString('101', 4)->toString());
        $this->assertSame('101', VarBitString::fromString(101, 4)->toString());
        $this->assertSame('1010010110110100111010101011010111001101011010',
            VarBitString::fromString('1010010110110100111010101011010111001101011010')->toString()
        );

        $this->assertSame('101', (string)VarBitString::fromString(101, 4));

        try {
            VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
            $this->fail('a warning is expected due to truncation');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
        }
        $vbs = @VarBitString::fromString('1010010110110100111010101011010111001101011010', 4);
        $this->assertSame('1010', $vbs->toString());
    }

    public function testGetLength()
    {
        $this->assertSame(0, VarBitString::fromString('')->getLength());
        $this->assertSame(1, VarBitString::fromString('0')->getLength());
        $this->assertSame(5, VarBitString::fromString('10010')->getLength());
        $this->assertSame(3, VarBitString::fromString('110')->getLength());
        $this->assertSame(5, VarBitString::fromString('10010', 5)->getLength());
        $this->assertSame(3, VarBitString::fromString('110', 5)->getLength());
        $this->assertSame(25, VarBitString::fromString('1111011000100111000110010', 25)->getLength());
        $this->assertSame(24, VarBitString::fromString('111101100010011100011001', 25)->getLength());
        $this->assertSame(1, VarBitString::fromString('1', 1025)->getLength());
    }

    public function testGetMaxLength()
    {
        $this->assertNull(VarBitString::fromString('')->getMaxLength());
        $this->assertNull(VarBitString::fromString('0')->getMaxLength());
        $this->assertNull(VarBitString::fromString('10010')->getMaxLength());
        $this->assertSame(5, VarBitString::fromString('10010', 5)->getMaxLength());
        $this->assertSame(5, VarBitString::fromString('110', 5)->getMaxLength());
        $this->assertSame(25, VarBitString::fromString('1111011000100111000110010', 25)->getMaxLength());
        $this->assertSame(25, VarBitString::fromString('111101100010011100011001', 25)->getMaxLength());
        $this->assertSame(1025, VarBitString::fromString('1', 1025)->getMaxLength());
    }

    public function testZero()
    {
        $this->assertTrue(VarBitString::fromString('0')->isZero());
        $this->assertTrue(VarBitString::fromString('00000')->isZero());
        $this->assertTrue(VarBitString::fromString('00000', 7)->isZero());
        $this->assertFalse(VarBitString::fromString('10010')->isZero());
        $this->assertFalse(VarBitString::fromString('1111011000100111000110010')->isZero());

        $this->assertFalse(VarBitString::fromString('0')->isNonZero());
        $this->assertFalse(VarBitString::fromString('00000')->isNonZero());
        $this->assertFalse(VarBitString::fromString('00000', 7)->isNonZero());
        $this->assertTrue(VarBitString::fromString('10010')->isNonZero());
        $this->assertTrue(VarBitString::fromString('1111011000100111000110010')->isNonZero());
    }

    public function testBitEquals()
    {
        $this->assertTrue(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('11001')));
        $this->assertTrue(VarBitString::fromString('11001', 8)->bitEquals(VarBitString::fromString('11001')));
        $this->assertTrue(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('11001', 9)));
        $this->assertTrue(VarBitString::fromString('11001', 14)->bitEquals(VarBitString::fromString('11001', 12)));

        $this->assertFalse(VarBitString::fromString('11001')->bitEquals(VarBitString::fromString('00011001')));
        $this->assertFalse(VarBitString::fromString('00011001')->bitEquals(VarBitString::fromString('11001')));
        $this->assertFalse(VarBitString::fromString('00011001')->bitEquals(VarBitString::fromString('0011001')));
        $this->assertFalse(VarBitString::fromString('10101')->bitEquals(VarBitString::fromString('00011001')));

        $this->assertTrue(
            VarBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                VarBitString::fromString('1111011000100111000110010')
            )
        );

        $this->assertFalse(
            VarBitString::fromString('1111011000100111000110010', 25)->bitEquals(
                VarBitString::fromString('1111011000110111000110010')
            )
        );
    }

    public function testIntersects()
    {
        $this->assertTrue(VarBitString::fromString('11010')->intersects(VarBitString::fromString('110')));
        $this->assertTrue(VarBitString::fromString('11010')->intersects(VarBitString::fromString('11010')));
        $this->assertTrue(VarBitString::fromString('110')->intersects(VarBitString::fromString('11010')));
        $this->assertTrue(VarBitString::fromString('1')->intersects(VarBitString::fromString('1101')));
        $this->assertTrue(VarBitString::fromString('1', 3)->intersects(VarBitString::fromString('1101')));

        $this->assertFalse(VarBitString::fromString('11010')->intersects(VarBitString::fromString('00100')));
        $this->assertFalse(VarBitString::fromString('1')->intersects(VarBitString::fromString('01')));
    }

    public function testConcat()
    {
        $this->assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 7)->concat(
                VarBitString::fromString('011')
            )
        ));

        $this->assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 7)->concat(
                VarBitString::fromString('011', 6)
            )
        ));

        $this->assertTrue(VarBitString::fromString('10001011')->equals(
            VarBitString::fromString('10001', 6)->concat(
                VarBitString::fromString('011', 6)
            )
        ));

        $str = VarBitString::fromString('100101');
        $this->assertTrue($str->equals($str->concat(VarBitString::fromString(''))));
        $this->assertTrue($str->equals(VarBitString::fromString('')->concat($str)));
    }

    public function testBitAnd()
    {
        $this->assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 7)->bitAnd(
                VarBitString::fromString('01101')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 7)->bitAnd(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('00001')->equals(
            VarBitString::fromString('10001', 6)->bitAnd(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitAnd(
                VarBitString::fromString('')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('1001000000100011000100010')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitAnd(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitAnd(VarBitString::fromString('101'));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitAnd(VarBitString::fromString('101', 5));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitOr()
    {
        $this->assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 7)->bitOr(
                VarBitString::fromString('01101')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 7)->bitOr(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('11101')->equals(
            VarBitString::fromString('10001', 6)->bitOr(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitOr(
                VarBitString::fromString('')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('1111111100101111000110011')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitOr(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitOr(VarBitString::fromString('101'));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitOr(VarBitString::fromString('101', 5));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitXor()
    {
        $this->assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 7)->bitXor(
                VarBitString::fromString('01101')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 7)->bitXor(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('11100')->equals(
            VarBitString::fromString('10001', 6)->bitXor(
                VarBitString::fromString('01101', 6)
            )
        ));

        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitXor(
                VarBitString::fromString('')
            )
        ));

        $this->assertTrue(FixedBitString::fromString('0110111100001100000010001')->equals(
            VarBitString::fromString('1111011000100111000110010')->bitXor(
                VarBitString::fromString('1001100100101011000100011')
            )
        ));

        try {
            VarBitString::fromString('10010')->bitXor(VarBitString::fromString('101'));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            VarBitString::fromString('10010')->bitXor(VarBitString::fromString('101', 5));
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testBitNot()
    {
        $this->assertTrue(FixedBitString::fromString('110010110')
            ->equals(VarBitString::fromString('001101001')->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('110010110')
            ->equals(VarBitString::fromString('001101001', 9)->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('0')
            ->equals(VarBitString::fromString('1')->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('')
            ->equals(VarBitString::fromString('')->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('111')
            ->equals(VarBitString::fromString('000', 6)->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('0000100111011000111001101')
            ->equals(VarBitString::fromString('1111011000100111000110010', 25)->bitNot())
        );
        $this->assertTrue(FixedBitString::fromString('0010')
            ->equals(VarBitString::fromString('1101')->bitNot())
        );
    }

    public function testBitShiftLeft()
    {
        $this->assertTrue(FixedBitString::fromString('0')
            ->equals(VarBitString::fromString('0')->bitShiftLeft(4))
        );
        $this->assertTrue(FixedBitString::fromString('10000')
            ->equals(VarBitString::fromString('11001')->bitShiftLeft(4))
        );
        $this->assertTrue(FixedBitString::fromString('01000')
            ->equals(VarBitString::fromString('110001', 6)->bitShiftLeft(3))
        );
        $this->assertTrue(FixedBitString::fromString('1100010011100011001000000')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(5))
        );
        $this->assertTrue(FixedBitString::fromString('1100010011100011001000000')
            ->equals(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftLeft(5))
        );
        $this->assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(0))
        );
        $this->assertTrue(FixedBitString::fromString('011010')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(-1))
        );
        $this->assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(123456789))
        );
        $this->assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftLeft(-123456789))
        );
    }

    public function testBitShiftRight()
    {
        $this->assertTrue(FixedBitString::fromString('00000')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(6))
        );
        $this->assertTrue(FixedBitString::fromString('00000')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(5))
        );
        $this->assertTrue(FixedBitString::fromString('00001')
            ->equals(VarBitString::fromString('11001')->bitShiftRight(4))
        );
        $this->assertTrue(FixedBitString::fromString('000110')
            ->equals(VarBitString::fromString('110101', 6)->bitShiftRight(3))
        );
        $this->assertTrue(FixedBitString::fromString('0000011110110001001110001')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(5))
        );
        $this->assertTrue(FixedBitString::fromString('0000011110110001001110001')
            ->equals(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftRight(5))
        );
        $this->assertTrue(FixedBitString::fromString('1111011000100111000110010')
            ->equals(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(0))
        );
        $this->assertTrue(FixedBitString::fromString('101000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(-1))
        );
        $this->assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(123456789))
        );
        $this->assertTrue(FixedBitString::fromString('000000')
            ->equals(VarBitString::fromString('110100')->bitShiftRight(-123456789))
        );
    }

    public function testBitRotateLeft()
    {
        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitRotateLeft(1)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011')->bitRotateLeft(1)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(1)
        ));

        $this->assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(0)
        ));

        $this->assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(5)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(6)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(-1)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(-6)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateLeft(123456789)
        ));
    }

    public function testBitRotateRight()
    {
        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('')->bitRotateRight(1)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011')->bitRotateRight(1)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(1)
        ));

        $this->assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(0)
        ));

        $this->assertTrue(FixedBitString::fromString('10011')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(5)
        ));

        $this->assertTrue(FixedBitString::fromString('11001')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(6)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(-1)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(-6)
        ));

        $this->assertTrue(FixedBitString::fromString('00111')->equals(
            VarBitString::fromString('10011', 8)->bitRotateRight(123456789)
        ));
    }

    public function testSubstring()
    {
        $this->assertTrue(FixedBitString::fromString('101001')->equals(
            VarBitString::fromString('1101001')->substring(2)
        ));

        $this->assertTrue(FixedBitString::fromString('1010')->equals(
            VarBitString::fromString('1101001')->substring(2, 4)
        ));

        try {
            VarBitString::fromString('1101001')->substring(2, -1);
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        $this->assertTrue(FixedBitString::fromString('')->equals(
            VarBitString::fromString('1101001')->substring(2, 0)
        ));

        $this->assertTrue(FixedBitString::fromString('1')->equals(
            VarBitString::fromString('1101001')->substring(-2, 4)
        ));

        $this->assertTrue(FixedBitString::fromString('1010')->equals(
            VarBitString::fromString('1101001', 15)->substring(2, 4)
        ));
    }

    public function testArrayAccess()
    {
        $small = VarBitString::fromString('1100101');
        $big = VarBitString::fromString('1111011000100111000110010');
        $limited = VarBitString::fromString('001100101', 10);

        $this->assertSame(1, $small[0]);
        $this->assertSame(1, $small[1]);
        $this->assertSame(0, $small[2]);
        $this->assertSame(1, $small[6]);
        $this->assertSame(1, $small[-1]);
        $this->assertSame(0, $small[-2]);
        $this->assertSame(1, $small[-6]);
        $this->assertSame(1, $small[-7]);
        $this->assertSame(0, $big[24]);
        $this->assertSame(1, $limited[8]);
        $this->assertNull($small[7]);
        $this->assertNull($small[8]);
        $this->assertNull($small[-8]);
        $this->assertNull($small[-9]);
        $this->assertNull($big[25]);
        $this->assertNull($limited[9]);

        $this->assertTrue(isset($small[0]));
        $this->assertTrue(isset($small[1]));
        $this->assertTrue(isset($small[2]));
        $this->assertTrue(isset($small[6]));
        $this->assertTrue(isset($small[-1]));
        $this->assertTrue(isset($small[-2]));
        $this->assertTrue(isset($small[-6]));
        $this->assertTrue(isset($small[-7]));
        $this->assertTrue(isset($big[24]));
        $this->assertTrue(isset($limited[8]));
        $this->assertFalse(isset($small[7]));
        $this->assertFalse(isset($small[8]));
        $this->assertFalse(isset($small[-8]));
        $this->assertFalse(isset($small[-9]));
        $this->assertFalse(isset($big[25]));
        $this->assertFalse(isset($limited[9]));

        try {
            $small[2] = 0;
            $this->fail('ImmutableException expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($small[2]);
            $this->fail('ImmutableException expected');
        } catch (ImmutableException $e) {
        }
    }
}
