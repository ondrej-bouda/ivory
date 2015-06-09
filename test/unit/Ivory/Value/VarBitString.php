<?php
namespace Ivory\Value;

use Ivory\ImmutableException;

class VarBitStringTest extends \PHPUnit_Framework_TestCase
{
	public function testEquals()
	{
		$this->assertTrue(VarBitString::fromString('00101')->equals(VarBitString::fromString('00101')));
		$this->assertTrue(VarBitString::fromString('1')->equals(VarBitString::fromString('1')));
		$this->assertFalse(VarBitString::fromString('101')->equals(VarBitString::fromString('1010')));
		$this->assertFalse(VarBitString::fromString('1')->equals(VarBitString::fromString('0')));
	}

	public function testFromString()
	{
		$this->assertTrue(VarBitString::fromString('10011000011011001')
				->equals(VarBitString::fromString('10011000011011001'))
		);
		$this->assertTrue(VarBitString::fromString('001')->equals(VarBitString::fromString('001')));
		$this->assertFalse(VarBitString::fromString('001', 3)->equals(VarBitString::fromString('001')));
		$this->assertFalse(VarBitString::fromString('001', 4)->equals(VarBitString::fromString('001')));
		$this->assertTrue(VarBitString::fromString(1101)->equals(VarBitString::fromString('1101')));

		try {
			VarBitString::fromString('10011', 0);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			VarBitString::fromString('10011', -4);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			VarBitString::fromString('10311');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }

		try {
			VarBitString::fromString(10311);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }
	}

	public function testGetLength()
	{
		$this->assertSame(0, VarBitString::fromString('')->getLength());
		$this->assertSame(1, VarBitString::fromString('0')->getLength());
		$this->assertSame(5, VarBitString::fromString('10010')->getLength());
		$this->assertSame(3, VarBitString::fromString('110')->getLength());
		$this->assertSame(5, VarBitString::fromString('10010', 5)->getLength());
		$this->assertSame(5, VarBitString::fromString('110', 5)->getLength());
		$this->assertSame(25, VarBitString::fromString('1111011000100111000110010', 25)->getLength());
		$this->assertSame(24, VarBitString::fromString('111101100010011100011001', 25)->getMaxLength());
		$this->assertSame(1, VarBitString::fromString('1', 1025)->getMaxLength());
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

		$this->assertTrue(
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
		// TODO
	}

	public function testBitAnd()
	{
		$resVV = (new BitString('1100101'))->bitAnd(new BitString('110'));
		$this->assertNull($resVV->getFixedLength());
		$this->assertSame('100', $resVV->toString());

		$resVV2 = (new BitString('110'))->bitAnd(new BitString('1100101'));
		$this->assertNull($resVV2->getFixedLength());
		$this->assertSame('100', $resVV2->toString());


		$resFV = (new BitString('10011', 8))->bitAnd(new BitString('111010'));
		$this->assertSame(8, $resFV->getFixedLength());
		$this->assertSame('00010010', $resFV->toString());

		$resFV2 = (new BitString('111010', 8))->bitAnd(new BitString('10011'));
		$this->assertSame(8, $resFV2->getFixedLength());
		$this->assertSame('00010010', $resFV2->toString());


		$resVF = (new BitString('10011'))->bitAnd(new BitString('111010', 8));
		$this->assertSame(8, $resVF->getFixedLength());
		$this->assertSame('00010010', $resVF->toString());

		$resVF2 = (new BitString('111010'))->bitAnd(new BitString('10011', 8));
		$this->assertSame(8, $resVF2->getFixedLength());
		$this->assertSame('00010010', $resVF2->toString());


		$resFF = (new BitString('10011', 8))->bitAnd(new BitString('111010', 8));
		$this->assertSame(8, $resFF->getFixedLength());
		$this->assertSame('00010010', $resFF->toString());

		$resFF2 = (new BitString('111010', 8))->bitAnd(new BitString('10011', 8));
		$this->assertSame(8, $resFF2->getFixedLength());
		$this->assertSame('00010010', $resFF2->toString());

		$this->assertSame(
            '1001000000100011000100010',
			(new BitString('1111011000100111000110010', 25))->bitAnd(
			    new BitString('1001100100101011000100011', 25)
			)->toString()
		);
	}

	public function testBitOr()
	{
		$resVV = (new BitString('1100101'))->bitOr(new BitString('110'));
		$this->assertNull($resVV->getFixedLength());
		$this->assertSame('1100111', $resVV->toString());

		$resVV2 = (new BitString('110'))->bitOr(new BitString('1100101'));
		$this->assertNull($resVV2->getFixedLength());
		$this->assertSame('1100111', $resVV2->toString());


		$resFV = (new BitString('10011', 8))->bitOr(new BitString('111010'));
		$this->assertSame(8, $resFV->getFixedLength());
		$this->assertSame('00111011', $resFV->toString());

		$resFV2 = (new BitString('111010', 8))->bitOr(new BitString('10011'));
		$this->assertSame(8, $resFV2->getFixedLength());
		$this->assertSame('00111011', $resFV2->toString());


		$resVF = (new BitString('10011'))->bitOr(new BitString('111010', 8));
		$this->assertSame(8, $resVF->getFixedLength());
		$this->assertSame('00111011', $resVF->toString());

		$resVF2 = (new BitString('111010'))->bitOr(new BitString('10011', 8));
		$this->assertSame(8, $resVF2->getFixedLength());
		$this->assertSame('00111011', $resVF2->toString());


		$resFF = (new BitString('10011', 8))->bitOr(new BitString('111010', 8));
		$this->assertSame(8, $resFF->getFixedLength());
		$this->assertSame('00111011', $resFF->toString());

		$resFF2 = (new BitString('111010', 8))->bitOr(new BitString('10011', 8));
		$this->assertSame(8, $resFF2->getFixedLength());
		$this->assertSame('00111011', $resFF2->toString());

		$this->assertSame(
            '1111111100101111000110011',
			(new BitString('1111011000100111000110010', 25))->bitOr(
				new BitString('1001100100101011000100011', 25)
			)->toString()
		);
	}

	public function testBitXor()
	{
		$resVV = (new BitString('1100101'))->bitXor(new BitString('110'));
		$this->assertNull($resVV->getFixedLength());
		$this->assertSame('1100011', $resVV->toString());

		$resVV2 = (new BitString('110'))->bitXor(new BitString('1100101'));
		$this->assertNull($resVV2->getFixedLength());
		$this->assertSame('1100011', $resVV2->toString());


		$resFV = (new BitString('10011', 8))->bitXor(new BitString('111010'));
		$this->assertSame(8, $resFV->getFixedLength());
		$this->assertSame('00101001', $resFV->toString());

		$resFV2 = (new BitString('111010', 8))->bitXor(new BitString('10011'));
		$this->assertSame(8, $resFV2->getFixedLength());
		$this->assertSame('00101001', $resFV2->toString());


		$resVF = (new BitString('10011'))->bitXor(new BitString('111010', 8));
		$this->assertSame(8, $resVF->getFixedLength());
		$this->assertSame('00101001', $resVF->toString());

		$resVF2 = (new BitString('111010'))->bitXor(new BitString('10011', 8));
		$this->assertSame(8, $resVF2->getFixedLength());
		$this->assertSame('00101001', $resVF2->toString());


		$resFF = (new BitString('10011', 8))->bitXor(new BitString('111010', 8));
		$this->assertSame(8, $resFF->getFixedLength());
		$this->assertSame('00101001', $resFF->toString());

		$resFF2 = (new BitString('111010', 8))->bitXor(new BitString('10011', 8));
		$this->assertSame(8, $resFF2->getFixedLength());
		$this->assertSame('00101001', $resFF2->toString());

		$this->assertSame(
			'0110111100001100000010001',
				VarBitString::fromString('1111011000100111000110010', 25)->bitXor(
				VarBitString::fromString('1001100100101011000100011', 25)
			)->toString()
		);
	}

	public function testBitNot()
	{
		$this->assertTrue(VarBitString::fromString('001101001')->bitNot()
				->equals(VarBitString::fromString('110010110'))
		);
		$this->assertTrue(VarBitString::fromString('001101001', 9)->bitNot()
				->equals(VarBitString::fromString('110010110'))
		);
		$this->assertTrue(VarBitString::fromString('1')->bitNot()
				->equals(VarBitString::fromString('0'))
		);
		$this->assertTrue(VarBitString::fromString('000', 6)->bitNot()
				->equals(VarBitString::fromString('111'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010', 25)->bitNot()
				->equals(VarBitString::fromString('0000100111011000111001101'))
		);
		$this->assertTrue(VarBitString::fromString('1101')->bitNot()
				->equals(VarBitString::fromString('0010'))
		);
	}

	public function testBitShiftLeft()
	{
		$this->assertTrue(VarBitString::fromString('0')->bitShiftLeft(4)
				->equals(VarBitString::fromString('0'))
		);
		$this->assertTrue(VarBitString::fromString('11001')->bitShiftLeft(4)
				->equals(VarBitString::fromString('10000'))
		);
		$this->assertTrue(VarBitString::fromString('110001', 6)->bitShiftLeft(3)
				->equals(VarBitString::fromString('01000'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(5)
				->equals(VarBitString::fromString('1100010011100011001000000'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftLeft(5)
				->equals(VarBitString::fromString('1100010011100011001000000'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010')->bitShiftLeft(0)
				->equals(VarBitString::fromString('1111011000100111000110010'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftLeft(-1)
				->equals(VarBitString::fromString('011010'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftLeft(123456789)
				->equals(VarBitString::fromString('000000'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftLeft(-123456789)
				->equals(VarBitString::fromString('000000'))
		);
	}

	public function testBitShiftRight()
	{
		$this->assertTrue(VarBitString::fromString('11001')->bitShiftRight(6)
				->equals(VarBitString::fromString('00000'))
		);
		$this->assertTrue(VarBitString::fromString('11001')->bitShiftRight(5)
				->equals(VarBitString::fromString('00000'))
		);
		$this->assertTrue(VarBitString::fromString('11001')->bitShiftRight(4)
				->equals(VarBitString::fromString('00001'))
		);
		$this->assertTrue(VarBitString::fromString('110101', 6)->bitShiftRight(3)
				->equals(VarBitString::fromString('000110'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(5)
				->equals(VarBitString::fromString('0000011110110001001110001'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010', 99)->bitShiftRight(5)
				->equals(VarBitString::fromString('0000011110110001001110001'))
		);
		$this->assertTrue(VarBitString::fromString('1111011000100111000110010')->bitShiftRight(0)
				->equals(VarBitString::fromString('1111011000100111000110010'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftRight(-1)
				->equals(VarBitString::fromString('101000'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftRight(123456789)
				->equals(VarBitString::fromString('000000'))
		);
		$this->assertTrue(VarBitString::fromString('110100')->bitShiftRight(-123456789)
				->equals(VarBitString::fromString('000000'))
		);
	}

	public function testBitRotates()
	{
		// TODO
	}

	public function testArrayAccess()
	{
		$small = VarBitString::fromString('1100101');
		$big = VarBitString::fromString('1111011000100111000110010');
		$limited = VarBitString::fromString('001100101', 10);

		$this->assertSame(1, $small[0]);
		$this->assertSame(0, $small[1]);
		$this->assertSame(1, $small[2]);
		$this->assertSame(1, $small[6]);
		$this->assertSame(1, $big[24]);
		$this->assertSame(0, $limited[8]);
		$this->assertNull($small[7]);
		$this->assertNull($small[8]);
		$this->assertNull($small[-1]);
		$this->assertNull($small[-4]);
		$this->assertNull($big[25]);
		$this->assertNull($limited[9]);

		$this->assertTrue(isset($small[0]));
		$this->assertTrue(isset($small[1]));
		$this->assertTrue(isset($small[2]));
		$this->assertTrue(isset($small[6]));
		$this->assertTrue(isset($big[24]));
		$this->assertTrue(isset($limited[8]));
		$this->assertFalse(isset($small[7]));
		$this->assertFalse(isset($small[8]));
		$this->assertFalse(isset($small[-1]));
		$this->assertFalse(isset($small[-4]));
		$this->assertFalse(isset($big[25]));
		$this->assertFalse(isset($limited[9]));

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
