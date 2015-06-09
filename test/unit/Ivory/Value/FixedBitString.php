<?php
namespace Ivory\Value;

use Ivory\ImmutableException;

// FIXME: revise the whole test file, as well as the FixedBitString interface
class FixedBitStringTest extends \PHPUnit_Framework_TestCase
{
	public function testFromString()
	{
		$this->assertSame('10011000011011001', FixedBitString::fromString('10011000011011001')->toString());
		$this->assertSame('001', FixedBitString::fromString('001')->toString());
		$this->assertSame('001', FixedBitString::fromString('001', 3)->toString());
		$this->assertSame('101', (new BitString(5))->toString());
		$this->assertSame('0101', (new BitString(5, 4))->toString());
		$this->assertSame('0', (new BitString(0))->toString());
		$this->assertSame('11000011011010001011100101', (new BitString(51225317))->toString());
		$this->assertSame('00000011000011011010001011100101', (new BitString(51225317, 32))->toString());
		$this->assertSame('111111111110000000001011110', (new BitString([7, 127, 0, 94]))->toString());
		$this->assertSame('1', (new BitString([1]))->toString());
		$this->assertSame('0', (new BitString([0]))->toString());
		$this->assertSame('1101', FixedBitString::fromString(1101)->toString());

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
			new BitString([]);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) { }
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
		$this->assertSame(24, FixedBitString::fromString('111101100010011100011001', 25)->getMaxLength());
		$this->assertSame(1, FixedBitString::fromString('1', 1025)->getMaxLength());
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
		$this->assertTrue(FixedBitString::fromString('11001')->bitEquals(FixedBitString::fromString('11001')));
		$this->assertTrue(FixedBitString::fromString('11001', 8)->bitEquals(FixedBitString::fromString('11001')));
		$this->assertTrue(FixedBitString::fromString('11001')->bitEquals(FixedBitString::fromString('11001', 9)));
		$this->assertTrue(FixedBitString::fromString('11001')->bitEquals(FixedBitString::fromString('00011001')));
		$this->assertTrue(FixedBitString::fromString('00011001')->bitEquals(FixedBitString::fromString('11001')));
		$this->assertTrue(FixedBitString::fromString('00011001')->bitEquals(FixedBitString::fromString('0011001')));
		$this->assertTrue(FixedBitString::fromString('11001', 14)->bitEquals(FixedBitString::fromString('11001', 12)));
		$this->assertTrue(FixedBitString::fromString(25)->bitEquals(FixedBitString::fromString('11001')));

		$this->assertFalse(FixedBitString::fromString('10010')->bitEquals(FixedBitString::fromString(10)));
		$this->assertFalse(FixedBitString::fromString('101')->bitEquals(FixedBitString::fromString(10)));
		$this->assertFalse(FixedBitString::fromString('10101')->bitEquals(FixedBitString::fromString('00011001')));

		$this->assertTrue(
				FixedBitString::fromString('1111011000100111000110010', 25)->bitEquals(
				FixedBitString::fromString('1111011000100111000110010')
				)
		);

		$this->assertTrue(
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

		$this->assertFalse(FixedBitString::fromString('11010')->intersects(FixedBitString::fromString('100')));
		$this->assertTrue(FixedBitString::fromString('1')->intersects(FixedBitString::fromString('1100')));
	}

	public function testToInt()
	{
		$this->assertSame(13, FixedBitString::fromString('1101')->toInt());
		$this->assertSame(13, FixedBitString::fromString('1101', 8)->toInt());
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

	public function testToOctetArray()
	{
		$this->assertSame([121, 38], (new BitString('10011001111001'))->toOctetArray());
		$this->assertSame([5], (new BitString('101'))->toOctetArray());
		$this->assertSame([0], (new BitString('0'))->toOctetArray());
		$this->assertSame(
			[77, 130, 75, 169, 129, 31, 174, 184, 14, 174, 90, 28, 108, 44, 1],
			(new BitString('10010110001101100000111000101101010101110000011101011100010101110000111111000000110101001010010111000001001001101'))->toOctetArray()
		);
	}

	public function testToNumber()
	{
		// TODO once the interface is specified
	}

	public function testConcat()
	{
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
				FixedBitString::fromString('1111011000100111000110010', 25)->bitXor(
				FixedBitString::fromString('1001100100101011000100011', 25)
			)->toString()
		);
	}

	public function testBitNot()
	{
		$this->assertSame('110010110', FixedBitString::fromString('001101001', 9)->bitNot()->toString());
		$this->assertSame('0', FixedBitString::fromString('1', 1)->bitNot()->toString());
		$this->assertSame('111111', FixedBitString::fromString('000', 6)->bitNot()->toString());
		$this->assertSame('0000100111011000111001101', FixedBitString::fromString('1111011000100111000110010', 25)->bitNot()->toString());
		$this->assertSame('0010', FixedBitString::fromString('1101')->bitNot()->toString());
	}

	public function testBitShiftRight()
	{
		$this->assertSame('0', FixedBitString::fromString('11001')->bitShiftRight(6)->toString());
		$this->assertSame('0', FixedBitString::fromString('11001')->bitShiftRight(5)->toString());
		$this->assertSame('1', FixedBitString::fromString('11001')->bitShiftRight(4)->toString());
		$this->assertSame('000110', FixedBitString::fromString('110101', 6)->bitShiftRight(3)->toString());
		$this->assertSame('11110110001001110001', FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(5)->toString());
		$this->assertSame('1111011000100111000110010', FixedBitString::fromString('1111011000100111000110010')->bitShiftRight(0)->toString());

		$this->assertSame('101100', FixedBitString::fromString('110110')->bitShiftRight(-1));
	}

	public function testBitShiftLeft()
	{
		$this->assertSame('0', FixedBitString::fromString('0')->bitShiftLeft(4)->toString());
		$this->assertSame('110010000', FixedBitString::fromString('11001')->bitShiftLeft(4)->toString());
		$this->assertSame('001000', FixedBitString::fromString('110001', 6)->bitShiftLeft(3)->toString());
		$this->assertSame('111101100010011100011001000000', FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(5)->toString());
		$this->assertSame('1100010011100011001000000', FixedBitString::fromString('1111011000100111000110010', 25)->bitShiftLeft(5)->toString());
		$this->assertSame('1111011000100111000110010', FixedBitString::fromString('1111011000100111000110010')->bitShiftLeft(0)->toString());

		$this->assertSame('011011', FixedBitString::fromString('110110')->bitShiftLeft(-1)->toString());
	}

	public function testBitRotates()
	{
		// TODO
	}

	public function testArrayAccess()
	{
		$small = FixedBitString::fromString('1100101');
		$big = FixedBitString::fromString('1111011000100111000110010');
		$limited = FixedBitString::fromString('001100101', 10);

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
