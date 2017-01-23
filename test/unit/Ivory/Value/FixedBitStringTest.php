<?php
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedOperationException;

class FixedBitStringTest extends \PHPUnit_Framework_TestCase
{
	public function testEquals()
	{
		$this->assertTrue(FixedBitString::fromString('00101')->equals(FixedBitString::fromString('00101')));
		$this->assertTrue(FixedBitString::fromString('1')->equals(FixedBitString::fromString('1')));
		$this->assertTrue(FixedBitString::fromString('00101', 5)->equals(FixedBitString::fromString('00101', 5)));
		$this->assertTrue(FixedBitString::fromString('001010')->equals(FixedBitString::fromString('00101', 6)));

		$this->assertFalse(FixedBitString::fromString('101')->equals(FixedBitString::fromString('1010')));
		$this->assertFalse(FixedBitString::fromString('1')->equals(FixedBitString::fromString('0')));
		$this->assertFalse(FixedBitString::fromString('00101', 5)->equals(FixedBitString::fromString('00101', 6)));
		$this->assertFalse(FixedBitString::fromString('000101')->equals(FixedBitString::fromString('00101', 6)));
	}

	public function testFromString()
	{
		$this->assertTrue(FixedBitString::fromString('10011000011011001')->equals(
			FixedBitString::fromString('10011000011011001')
		));

		$this->assertTrue(FixedBitString::fromString('')->equals(FixedBitString::fromString('')));
		$this->assertTrue(FixedBitString::fromString('001')->equals(FixedBitString::fromString('001')));
		$this->assertTrue(FixedBitString::fromString('001')->equals(FixedBitString::fromString('001', 3)));
		$this->assertTrue(FixedBitString::fromString('0010')->equals(FixedBitString::fromString('001', 4)));
		$this->assertTrue(FixedBitString::fromString('1101')->equals(FixedBitString::fromString(1101)));
		$this->assertTrue(FixedBitString::fromString('11010')->equals(FixedBitString::fromString(1101, 5)));

		try {
			FixedBitString::fromString('10011', 0);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			FixedBitString::fromString('10011', -4);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			FixedBitString::fromString('10311');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			FixedBitString::fromString(10311);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
			$this->fail('a warning is expected due to truncation');
		}
		catch (\PHPUnit_Framework_Error_Warning $e) {
		}
		$fbs = @FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
		$this->assertTrue(FixedBitString::fromString('1010')->equals($fbs));
	}

	public function testToString()
	{
		$this->assertSame('', FixedBitString::fromString('')->toString());
		$this->assertSame('1010', FixedBitString::fromString('101', 4)->toString());
		$this->assertSame('1010', FixedBitString::fromString(101, 4)->toString());
		$this->assertSame('1010010110110100111010101011010111001101011010',
			FixedBitString::fromString('1010010110110100111010101011010111001101011010')->toString()
		);

		$this->assertSame('1010', (string)FixedBitString::fromString('101', 4));

		try {
			FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
			$this->fail('a warning is expected due to truncation');
		}
		catch (\PHPUnit_Framework_Error_Warning $e) {
		}
		$fbs = @FixedBitString::fromString('1010010110110100111010101011010111001101011010', 4);
		$this->assertSame('1010', $fbs->toString());
	}

	public function testFromInt()
	{
		$this->assertTrue(FixedBitString::fromString('101')->equals(FixedBitString::fromInt(5, 3)));
		$this->assertTrue(FixedBitString::fromString('00101')->equals(FixedBitString::fromInt(5, 5)));
		$this->assertTrue(FixedBitString::fromString('01')->equals(FixedBitString::fromInt(5, 2)));
		$this->assertTrue(FixedBitString::fromString('0')->equals(FixedBitString::fromInt(0, 1)));
		$this->assertTrue(FixedBitString::fromString('0000')->equals(FixedBitString::fromInt(0, 4)));
		$this->assertTrue(FixedBitString::fromString('1111010110')->equals(FixedBitString::fromInt(-42, 10)));
		$this->assertTrue(FixedBitString::fromString('1010110')->equals(FixedBitString::fromInt(-42, 7)));
		$this->assertTrue(FixedBitString::fromString('010110')->equals(FixedBitString::fromInt(-42, 6)));

		$this->assertTrue(FixedBitString::fromString('000000011000011011010001011100101')
			->equals(FixedBitString::fromInt(51225317, 33))
		);
		$this->assertTrue(FixedBitString::fromString('111111100111100100101110100011011')
			->equals(FixedBitString::fromInt(-51225317, 33))
		);

		try {
			FixedBitString::fromInt('10011', 0);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			FixedBitString::fromInt('10011', -4);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }
	}

	public function testToInt()
	{
		$this->assertSame(13, FixedBitString::fromString('1101')->toInt());
		$this->assertSame(208, FixedBitString::fromString('1101', 8)->toInt());
		$this->assertSame(13, FixedBitString::fromString('0001101')->toInt());
		$this->assertSame(0, FixedBitString::fromString('0')->toInt());
		$this->assertSame(2147483647, FixedBitString::fromString('1111111111111111111111111111111')->toInt());
		if (PHP_INT_SIZE == 4) {
			$this->assertSame(5, FixedBitString::fromString('10000000000000000000000000000101')->toInt());
			$this->assertSame(5, FixedBitString::fromString('110000000000000000000000000000101')->toInt());
			$this->assertSame(1, FixedBitString::fromString('1000000000000000000000000000000000000000000000000000000000000001')->toInt());
			$this->assertSame(0, FixedBitString::fromString('110011000000000000000000000000000000000000000000000000000000000000000')->toInt());
		}
		elseif (PHP_INT_SIZE == 8) {
			$this->assertSame(2147483653, FixedBitString::fromString('10000000000000000000000000000101')->toInt());
			$this->assertSame(6442450949, FixedBitString::fromString('110000000000000000000000000000101')->toInt());
			$this->assertSame(1, FixedBitString::fromString('1000000000000000000000000000000000000000000000000000000000000001')->toInt());
			$this->assertSame(0, FixedBitString::fromString('110011000000000000000000000000000000000000000000000000000000000000000')->toInt());
		}
	}

	public function testFromNumber()
	{
		// TODO once the interface is specified
	}

	public function testToNumber()
	{
		// TODO once the interface is specified
	}

	public function testGetLength()
	{
		$this->assertSame(0, FixedBitString::fromString('')->getLength());
		$this->assertSame(1, FixedBitString::fromString('0')->getLength());
		$this->assertSame(5, FixedBitString::fromString('10010')->getLength());
		$this->assertSame(3, FixedBitString::fromString('110')->getLength());
		$this->assertSame(5, FixedBitString::fromString('10010', 5)->getLength());
		$this->assertSame(5, FixedBitString::fromString('110', 5)->getLength());
		$this->assertSame(25, FixedBitString::fromString('1111011000100111000110010', 25)->getLength());
		$this->assertSame(25, FixedBitString::fromString('111101100010011100011001', 25)->getLength());
		$this->assertSame(1025, FixedBitString::fromString('1', 1025)->getLength());
	}

	public function testZero()
	{
		$this->assertFalse(FixedBitString::fromString('10010')->isZero());
		$this->assertTrue(FixedBitString::fromString('0')->isZero());
		$this->assertTrue(FixedBitString::fromString('00000')->isZero());
		$this->assertTrue(FixedBitString::fromString('00000', 7)->isZero());
		$this->assertFalse(FixedBitString::fromString('1111011000100111000110010')->isZero());

		$this->assertTrue(FixedBitString::fromString('10010')->isNonZero());
		$this->assertFalse(FixedBitString::fromString('0')->isNonZero());
		$this->assertFalse(FixedBitString::fromString('00000')->isNonZero());
		$this->assertFalse(FixedBitString::fromString('00000', 7)->isNonZero());
		$this->assertTrue(FixedBitString::fromString('1111011000100111000110010')->isNonZero());
	}

	public function testBitEquals()
	{
		$this->assertTrue(FixedBitString::fromString('')->bitEquals(FixedBitString::fromString('')));
		$this->assertTrue(FixedBitString::fromString('11001')->bitEquals(FixedBitString::fromString('11001')));
		$this->assertTrue(FixedBitString::fromString('11001', 8)->bitEquals(FixedBitString::fromString('11001000')));
		$this->assertTrue(FixedBitString::fromString('101')->bitEquals(FixedBitString::fromString(101)));

		$this->assertFalse(FixedBitString::fromString('11001', 8)->bitEquals(FixedBitString::fromString('11001')));
		$this->assertFalse(FixedBitString::fromString('00011001')->bitEquals(FixedBitString::fromString('11001')));
		$this->assertFalse(FixedBitString::fromString('11001', 14)->bitEquals(FixedBitString::fromString('11001', 12)));
		$this->assertFalse(FixedBitString::fromString('10010')->bitEquals(FixedBitString::fromString(10)));
		$this->assertFalse(FixedBitString::fromString('10101')->bitEquals(FixedBitString::fromString('00011001')));

		$this->assertTrue(
				FixedBitString::fromString('1111011000100111000110010', 25)->bitEquals(
				FixedBitString::fromString('1111011000100111000110010')
				)
		);

		$this->assertFalse(
				FixedBitString::fromString('1111011000100111000110010', 25)->bitEquals(
					FixedBitString::fromString('1111011000110111000110010')
				)
		);
	}

	public function testIntersects()
	{
		$this->assertTrue(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('110')));
		$this->assertTrue(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('11010')));
		$this->assertTrue(FixedBitString::fromString('110')->intersects(FixedBitString::fromString('11010')));
		$this->assertTrue(FixedBitString::fromString('1')->intersects(FixedBitString::fromString('1101')));
		$this->assertTrue(FixedBitString::fromString('1', 3)->intersects(FixedBitString::fromString('1101')));

		$this->assertFalse(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('00100')));
		$this->assertFalse(FixedBitString::fromString('001', 4)->intersects(FixedBitString::fromString('1100')));
	}

	public function testConcat()
	{
		$this->assertTrue(VarBitString::fromString('10001011')->equals(
			FixedBitString::fromString('10001')->concat(FixedBitString::fromString('011'))
		));

		$this->assertTrue(VarBitString::fromString('1000100011')->equals(
			FixedBitString::fromString('10001', 7)->concat(FixedBitString::fromString('011'))
		));

		$this->assertTrue(VarBitString::fromString('1000101100')->equals(
			FixedBitString::fromString('10001')->concat(FixedBitString::fromString('011', 5))
		));

		$exp = VarBitString::fromString('100101');
		$str = FixedBitString::fromString('100101');
		$this->assertTrue($exp->equals($str->concat(FixedBitString::fromString(''))));
		$this->assertTrue($exp->equals(FixedBitString::fromString('')->concat($str)));
	}

	public function testBitAnd()
	{
		$this->assertTrue(FixedBitString::fromString('')->equals(
			FixedBitString::fromString('')->bitAnd(
				FixedBitString::fromString('')
			)
		));

		$this->assertTrue(FixedBitString::fromString('1000000')->equals(
			FixedBitString::fromString('1100101')->bitAnd(
				FixedBitString::fromString('101', 7)
			)
		));

		$this->assertTrue(FixedBitString::fromString('0')->equals(
			FixedBitString::fromString('0')->bitAnd(
				FixedBitString::fromString('1')
			)
		));

		$this->assertTrue(FixedBitString::fromString('1001000000100011000100010')->equals(
			FixedBitString::fromString('1111011000100111000110010')->bitAnd(
				FixedBitString::fromString('1001100100101011000100011')
			)
		));

		try {
			FixedBitString::fromString('10010')->bitAnd(FixedBitString::fromString('101'));
			$this->fail('UndefinedOperationException expected');
		}
		catch (UndefinedOperationException $e) {
		}
	}

	public function testBitOr()
	{
		$this->assertTrue(FixedBitString::fromString('')->equals(
			FixedBitString::fromString('')->bitOr(
				FixedBitString::fromString('')
			)
		));

		$this->assertTrue(FixedBitString::fromString('1110101')->equals(
			FixedBitString::fromString('1100101')->bitOr(
				FixedBitString::fromString('101', 7)
			)
		));

		$this->assertTrue(FixedBitString::fromString('1')->equals(
			FixedBitString::fromString('0')->bitOr(
				FixedBitString::fromString('1')
			)
		));

		$this->assertTrue(FixedBitString::fromString('1111111100101111000110011')->equals(
			FixedBitString::fromString('1111011000100111000110010')->bitOr(
				FixedBitString::fromString('1001100100101011000100011')
			)
		));

		try {
			FixedBitString::fromString('10010')->bitOr(FixedBitString::fromString('101'));
			$this->fail('UndefinedOperationException expected');
		}
		catch (UndefinedOperationException $e) {
		}
	}

	public function testBitXor()
	{
		$this->assertTrue(FixedBitString::fromString('')->equals(
			FixedBitString::fromString('')->bitXor(
				FixedBitString::fromString('')
			)
		));

		$this->assertTrue(FixedBitString::fromString('0110101')->equals(
			FixedBitString::fromString('1100101')->bitXor(
				FixedBitString::fromString('101', 7)
			)
		));

		$this->assertTrue(FixedBitString::fromString('1')->equals(
			FixedBitString::fromString('0')->bitXor(
				FixedBitString::fromString('1')
			)
		));

		$this->assertTrue(FixedBitString::fromString('0110111100001100000010001')->equals(
			FixedBitString::fromString('1111011000100111000110010')->bitXor(
				FixedBitString::fromString('1001100100101011000100011')
			)
		));

		try {
			FixedBitString::fromString('10010')->bitXor(FixedBitString::fromString('101'));
			$this->fail('UndefinedOperationException expected');
		}
		catch (UndefinedOperationException $e) {
		}
	}

	public function testBitNot()
	{
		$this->assertTrue(FixedBitString::fromString('110010110')
			->equals(FixedBitString::fromString('001101001')->bitNot())
		);
		$this->assertTrue(FixedBitString::fromString('0')
			->equals(FixedBitString::fromString('1')->bitNot())
		);
		$this->assertTrue(FixedBitString::fromString('')
			->equals(FixedBitString::fromString('')->bitNot())
		);
		$this->assertTrue(FixedBitString::fromString('111111')
			->equals(FixedBitString::fromString('000', 6)->bitNot())
		);
		$this->assertTrue(FixedBitString::fromString('0010')
			->equals(FixedBitString::fromString('1101')->bitNot())
		);
		$this->assertTrue(FixedBitString::fromString('00001001110110001110011011')
			->equals(FixedBitString::fromString('1111011000100111000110010', 26)->bitNot())
		);
	}

	public function testBitShiftLeft()
	{
		$this->assertTrue(FixedBitString::fromString('0')
			->equals(FixedBitString::fromString('0')->bitShiftLeft(4))
		);
		$this->assertTrue(FixedBitString::fromString('000100')
			->equals(FixedBitString::fromString('110001')->bitShiftLeft(2))
		);
		$this->assertTrue(FixedBitString::fromString('0100000000')
			->equals(FixedBitString::fromString('11001', 10)->bitShiftLeft(3))
		);
		$this->assertTrue(FixedBitString::fromString('1100010011100011001000000')
			->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(5))
		);
		$this->assertTrue(FixedBitString::fromString('1111011000100111000110010')
			->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(0))
		);
		$this->assertTrue(FixedBitString::fromString('001101')
			->equals(FixedBitString::fromString('110110')->bitShiftLeft(-2))
		);
		$this->assertTrue(FixedBitString::fromString('000000')
			->equals(FixedBitString::fromString('110110')->bitShiftLeft(123456789))
		);
		$this->assertTrue(FixedBitString::fromString('000000')
			->equals(FixedBitString::fromString('110110')->bitShiftLeft(-123456789))
		);
	}

	public function testBitShiftRight()
	{
		$this->assertTrue(FixedBitString::fromString('00000')
			->equals(FixedBitString::fromString('11001')->bitShiftRight(6))
		);
		$this->assertTrue(FixedBitString::fromString('00000')
			->equals(FixedBitString::fromString('11001')->bitShiftRight(5))
		);
		$this->assertTrue(FixedBitString::fromString('00001')
			->equals(FixedBitString::fromString('11001')->bitShiftRight(4))
		);
		$this->assertTrue(FixedBitString::fromString('001101')
			->equals(FixedBitString::fromString('110101')->bitShiftRight(2))
		);
		$this->assertTrue(FixedBitString::fromString('0000011110110001001110001')
			->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(5))
		);
		$this->assertTrue(FixedBitString::fromString('1111011000100111000110010')
			->equals(FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(0))
		);
		$this->assertTrue(FixedBitString::fromString('')
			->equals(FixedBitString::fromString('')->bitShiftRight(0))
		);
		$this->assertTrue(FixedBitString::fromString('')
			->equals(FixedBitString::fromString('')->bitShiftRight(4))
		);
		$this->assertTrue(FixedBitString::fromString('101100')
			->equals(FixedBitString::fromString('110110')->bitShiftRight(-1))
		);
		$this->assertTrue(FixedBitString::fromString('000000')
			->equals(FixedBitString::fromString('101100')->bitShiftRight(-123456789))
		);
		$this->assertTrue(FixedBitString::fromString('000000')
			->equals(FixedBitString::fromString('101100')->bitShiftRight(123456789))
		);
	}

	public function testBitRotateLeft()
	{
		$this->assertTrue(FixedBitString::fromString('')
			->equals(FixedBitString::fromString('')->bitRotateLeft(1))
		);
		$this->assertTrue(FixedBitString::fromString('100110')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(0))
		);
		$this->assertTrue(FixedBitString::fromString('011010')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(2))
		);
		$this->assertTrue(FixedBitString::fromString('011010')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(8))
		);
		$this->assertTrue(FixedBitString::fromString('010011')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(-1))
		);
		$this->assertTrue(FixedBitString::fromString('001101')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(1234567))
		);
		$this->assertTrue(FixedBitString::fromString('010011')
			->equals(FixedBitString::fromString('100110')->bitRotateLeft(-1234567))
		);
	}

	public function testBitRotateRight()
	{
		$this->assertTrue(FixedBitString::fromString('')
			->equals(FixedBitString::fromString('')->bitRotateRight(1))
		);
		$this->assertTrue(FixedBitString::fromString('100110')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(0))
		);
		$this->assertTrue(FixedBitString::fromString('101001')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(2))
		);
		$this->assertTrue(FixedBitString::fromString('101001')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(8))
		);
		$this->assertTrue(FixedBitString::fromString('001101')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(-1))
		);
		$this->assertTrue(FixedBitString::fromString('010011')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(1234567))
		);
		$this->assertTrue(FixedBitString::fromString('001101')
			->equals(FixedBitString::fromString('100110')->bitRotateRight(-1234567))
		);
	}

	public function testSubstring()
	{
		$this->assertTrue(FixedBitString::fromString('101001')->equals(
			FixedBitString::fromString('1101001')->substring(2)
		));

		$this->assertTrue(FixedBitString::fromString('1010')->equals(
			FixedBitString::fromString('1101001')->substring(2, 4)
		));

		try {
			FixedBitString::fromString('1101001')->substring(2, -1);
			$this->fail('UndefinedOperationException expected');
		}
		catch (UndefinedOperationException $e) {
		}

		$this->assertTrue(FixedBitString::fromString('')->equals(
			FixedBitString::fromString('1101001')->substring(2, 0)
		));

		$this->assertTrue(FixedBitString::fromString('1')->equals(
			FixedBitString::fromString('1101001')->substring(-2, 4)
		));
	}

	public function testArrayAccess()
	{
		$small = FixedBitString::fromString('1100101');
		$big = FixedBitString::fromString('1111011000100111000110010');
		$padded = FixedBitString::fromString('001100101', 10);

		$this->assertSame(1, $small[0]);
		$this->assertSame(1, $small[1]);
		$this->assertSame(0, $small[2]);
		$this->assertSame(1, $small[6]);
        $this->assertSame(1, $small[-1]);
        $this->assertSame(0, $small[-2]);
        $this->assertSame(1, $small[-6]);
        $this->assertSame(1, $small[-7]);
		$this->assertSame(0, $big[24]);
		$this->assertSame(1, $padded[8]);
		$this->assertSame(0, $padded[9]);
		$this->assertNull($small[7]);
		$this->assertNull($small[8]);
		$this->assertNull($small[-8]);
		$this->assertNull($small[-9]);
		$this->assertNull($big[25]);
		$this->assertNull($padded[10]);

		$this->assertTrue(isset($small[0]));
		$this->assertTrue(isset($small[1]));
		$this->assertTrue(isset($small[2]));
        $this->assertTrue(isset($small[6]));
        $this->assertTrue(isset($small[-1]));
        $this->assertTrue(isset($small[-2]));
        $this->assertTrue(isset($small[-6]));
        $this->assertTrue(isset($small[-7]));
		$this->assertTrue(isset($big[24]));
		$this->assertTrue(isset($padded[8]));
		$this->assertTrue(isset($padded[9]));
		$this->assertFalse(isset($small[7]));
		$this->assertFalse(isset($small[8]));
		$this->assertFalse(isset($small[-8]));
		$this->assertFalse(isset($small[-9]));
		$this->assertFalse(isset($big[25]));
		$this->assertFalse(isset($padded[10]));

		try {
			$small[2] = 0;
			$this->fail('ImmutableException expected');
		}
		catch (ImmutableException $e) {
		}

		try {
			unset($small[2]);
			$this->fail('ImmutableException expected');
		}
		catch (ImmutableException $e) {
		}
	}
}
