<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function testEquals()
    {
        self::assertEquals(Decimal::fromNumber('1'), Decimal::fromNumber(1));
        self::assertTrue(Decimal::fromNumber('1')->equals(1));
        self::assertEquals(0, Decimal::fromNumber('1')->compareTo(1));
        self::assertFalse(Decimal::fromNumber('1')->lessThan(1));
        self::assertFalse(Decimal::fromNumber('1')->greaterThan(1));
        self::assertNotEquals(Decimal::fromNumber('1'), Decimal::fromNumber(2));
        self::assertFalse(Decimal::fromNumber('1')->equals(2));
        self::assertEquals(-1, Decimal::fromNumber('1')->compareTo(2));
        self::assertTrue(Decimal::fromNumber('1')->lessThan(2));
        self::assertFalse(Decimal::fromNumber('1')->greaterThan(2));

        self::assertEquals(Decimal::zero(), Decimal::fromNumber(0));
        self::assertTrue(Decimal::zero()->equals(0));
        self::assertEquals(0, Decimal::zero()->compareTo(0));
        self::assertFalse(Decimal::zero()->lessThan(0));
        self::assertFalse(Decimal::zero()->greaterThan(0));
        self::assertNotEquals(Decimal::zero(), Decimal::fromNumber(0.1));
        self::assertFalse(Decimal::zero()->equals(0.1));

        self::assertEquals(Decimal::fromNumber('123.456'), Decimal::fromNumber(123456e-3));
        self::assertTrue(Decimal::fromNumber('123.456')->equals(123456e-3));
        self::assertEquals(0, Decimal::fromNumber('123.456')->compareTo(123456e-3));
        self::assertFalse(Decimal::fromNumber('123.456')->lessThan(123456e-3));
        self::assertFalse(Decimal::fromNumber('123.456')->greaterThan(123456e-3));
        self::assertNotEquals(Decimal::fromNumber('123.456'), Decimal::fromNumber(123457e-3));
        self::assertFalse(Decimal::fromNumber('123.456')->equals(123457e-3));
        self::assertEquals(-1, Decimal::fromNumber('123.456')->compareTo(123457e-3));
        self::assertTrue(Decimal::fromNumber('123.456')->lessThan(123457e-3));
        self::assertFalse(Decimal::fromNumber('123.456')->greaterThan(123457e-3));
        self::assertEquals(1, Decimal::fromNumber('123.457')->compareTo(123456e-3));
        self::assertFalse(Decimal::fromNumber('123.457')->lessThan(123456e-3));
        self::assertTrue(Decimal::fromNumber('123.457')->greaterThan(123456e-3));

        self::assertEquals(Decimal::createNaN(), Decimal::createNaN());
        self::assertNotEquals(Decimal::createNaN(), Decimal::fromNumber(0));
        self::assertNotEquals(Decimal::createNaN(), Decimal::fromNumber(1));
        self::assertNotEquals(Decimal::fromNumber(0), Decimal::createNaN());
        self::assertNotEquals(Decimal::fromNumber(1), Decimal::createNaN());
        self::assertTrue(Decimal::createNaN()->equals(Decimal::createNaN()));
        self::assertFalse(Decimal::createNaN()->equals(0));
        self::assertFalse(Decimal::createNaN()->equals(1));
        self::assertEquals(0, Decimal::createNaN()->compareTo(Decimal::createNaN()));
        self::assertTrue(Decimal::createNaN()->greaterThan(0));
        self::assertFalse(Decimal::fromNumber(0)->greaterThan(Decimal::createNaN()));
        self::assertTrue(Decimal::fromNumber(0)->lessThan(Decimal::createNaN()));
        self::assertFalse(Decimal::createNaN()->lessThan(0));

        self::assertEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234, 2));
        self::assertNotEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234));
        self::assertNotEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234.00));
        self::assertTrue(Decimal::fromNumber('1234.00')->equals(Decimal::fromNumber(1234, 2)));
        self::assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(Decimal::fromNumber(1234, 2)));
        self::assertTrue(Decimal::fromNumber('1234.00')->equals(1234));
        self::assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(1234));
        self::assertTrue(Decimal::fromNumber('1234.00')->equals(1234.00));
        self::assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(1234.00));

        self::assertEquals(Decimal::fromNumber('123.95', 1), Decimal::fromNumber('124.0'));
        self::assertNotEquals(Decimal::fromNumber('123.95', 2), Decimal::fromNumber('124.00'));
        self::assertTrue(Decimal::fromNumber('123.95', 1)->equals('124.0'));
        self::assertFalse(Decimal::fromNumber('123.95', 2)->equals('124.00'));

        self::assertEquals(Decimal::fromNumber(-5, 2), Decimal::fromNumber('-5.00'));
        self::assertNotEquals(Decimal::fromNumber(-5, 2), Decimal::fromNumber('-5'));
        self::assertTrue(Decimal::fromNumber(-5, 2)->equals(Decimal::fromNumber('-5.00')));
        self::assertTrue(Decimal::fromNumber(-5, 2)->equals(Decimal::fromNumber('-5')));
    }

    /**
     * @requires extension gmp
     */
    public function testEqualsGmp()
    {
        self::assertEquals(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890')),
            Decimal::fromNumber('123456789012345678901234567890')
        );
        self::assertTrue(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->equals('123456789012345678901234567890')
        );
        self::assertEquals(
            0,
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->compareTo('123456789012345678901234567890')
        );
        self::assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->lessThan('123456789012345678901234567890')
        );
        self::assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->greaterThan('123456789012345678901234567890')
        );
        self::assertNotEquals(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890')),
            Decimal::fromNumber('123456789012345678901234567891')
        );
        self::assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->equals('123456789012345678901234567891')
        );
        self::assertEquals(
            -1,
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->compareTo('123456789012345678901234567891')
        );
        self::assertTrue(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->lessThan('123456789012345678901234567891')
        );
        self::assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->greaterThan('123456789012345678901234567891')
        );
    }

    public function testFromNumber()
    {
        self::assertSame('1.5', Decimal::fromNumber('1.49', 1)->toString());
        self::assertSame('1.5', Decimal::fromNumber('1.45', 1)->toString());
        self::assertSame('1.4', Decimal::fromNumber('1.4499999999999999999999999999999999999999999', 1)->toString());
        self::assertSame(
            '1.450000000000000000000000000000000000000000',
            Decimal::fromNumber('1.4499999999999999999999999999999999999999999', 42)->toString()
        );

        self::assertSame('0', Decimal::fromNumber(0.1, 0)->toString());
        self::assertSame('0', Decimal::fromNumber(0)->toString());
        self::assertSame('0', Decimal::fromNumber(-.4999, 0)->toString());
        self::assertSame('-1', Decimal::fromNumber(-.5, 0)->toString());

        self::assertSame('1.420000', Decimal::fromNumber(1.42, 6)->toString());

        self::assertSame('0', Decimal::fromNumber(-0)->toString());
        self::assertSame('0.12', Decimal::fromNumber('.12')->toString());
        self::assertSame('-0.12', Decimal::fromNumber('-.12')->toString());

        self::assertSame(
            '123456789123456789123456789',
            Decimal::fromNumber(Decimal::fromNumber('123456789123456789123456789'))->toString()
        );
        $obj = new class {
            public function __toString()
            {
                return '    0042    ';
            }
        };
        self::assertSame('42', Decimal::fromNumber($obj)->toString());

        self::assertSame('123400000000000000000000000', Decimal::fromNumber(12.34e25)->toString());
        self::assertSame('1234.56', Decimal::fromNumber(123.456e1)->toString());
        self::assertSame('1.23456', Decimal::fromNumber(123.456e-2)->toString());
        self::assertSame('-0.000000000000000000000001234', Decimal::fromNumber(-12.34e-25)->toString());

        self::assertSame(0, Decimal::fromNumber('1234')->getScale());
        self::assertSame(0, Decimal::fromNumber('1234.02', 0)->getScale());
        self::assertSame(2, Decimal::fromNumber('1234.02')->getScale());
        self::assertSame(2, Decimal::fromNumber('1234.00')->getScale());
        self::assertSame('1234.00', Decimal::fromNumber('1234.00')->toString());
        self::assertSame(42, Decimal::fromNumber('1234.00', 42)->getScale());
        self::assertSame(2, Decimal::fromNumber('-123.45')->getScale());
        self::assertSame(60,
            Decimal::fromNumber('.123456789012345678901234567890123456789012345678901234567890')->getScale()
        );

        self::assertSame('1234', Decimal::fromNumber('01234   ')->toString());
        self::assertSame('0.00', Decimal::fromNumber('00.00')->toString());
        self::assertSame('0.0', Decimal::fromNumber('0.0')->toString());
        self::assertSame('0', Decimal::fromNumber('   00')->toString());
        self::assertSame('-1234', Decimal::fromNumber('  -01234 ')->toString());
        self::assertSame('0', Decimal::fromNumber('-0')->toString());
        self::assertSame('0', Decimal::fromNumber('-00')->toString());
        self::assertSame('0.00', Decimal::fromNumber(' -00.00  ')->toString());
        self::assertSame('0.0', Decimal::fromNumber('-0.0')->toString());

        try {
            Decimal::fromNumber(null);
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Decimal::fromNumber('123456A7');
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Decimal::fromNumber(42.3, -1);
            self::fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * @requires extension gmp
     */
    public function testFromNumberGmp()
    {
        self::assertSame(
            '123456789123456789123456789',
            Decimal::fromNumber(gmp_init('123456789123456789123456789'))->toString()
        );
    }

    public function testNan()
    {
        self::assertSame('NaN', Decimal::createNaN()->toString());
        self::assertNull(Decimal::createNaN()->getScale());

        self::assertTrue(Decimal::createNaN()->isNaN());
        self::assertFalse(Decimal::zero()->isNaN());
    }

    public function testZero()
    {
        self::assertSame('0', Decimal::zero()->toString());
        self::assertSame(0, Decimal::zero()->getScale());

        self::assertTrue(Decimal::zero()->isZero());
        self::assertTrue(Decimal::fromNumber(0)->isZero());
        self::assertTrue(Decimal::fromNumber(0.1, 0)->isZero());
        self::assertFalse(Decimal::createNaN()->isZero());
        self::assertFalse(Decimal::fromNumber(1)->isZero());
        self::assertFalse(Decimal::fromNumber(-0.5)->isZero());
    }

    public function testIsPositive()
    {
        self::assertTrue(Decimal::fromNumber(0.1)->isPositive());
        self::assertFalse(Decimal::fromNumber(0.1, 0)->isPositive());
        self::assertFalse(Decimal::zero()->isPositive());
        self::assertFalse(Decimal::fromNumber(-0.1)->isPositive());
        self::assertFalse(Decimal::createNaN()->isPositive());
    }

    public function testIsNegative()
    {
        self::assertTrue(Decimal::fromNumber(-0.1)->isNegative());
        self::assertFalse(Decimal::fromNumber(-0.1, 0)->isNegative());
        self::assertFalse(Decimal::zero()->isNegative());
        self::assertFalse(Decimal::fromNumber(0.1)->isNegative());
        self::assertFalse(Decimal::createNaN()->isNegative());
    }

    public function testIsInteger()
    {
        self::assertTrue(Decimal::zero()->isInteger());
        self::assertTrue(Decimal::fromNumber(12)->isInteger());
        self::assertTrue(Decimal::fromNumber(-1)->isInteger());
        self::assertTrue(Decimal::fromNumber(1.2, 0)->isInteger());
        self::assertTrue(Decimal::fromNumber('123456789123456789123456798')->isInteger());

        self::assertFalse(Decimal::createNaN()->isInteger());
        self::assertFalse(Decimal::fromNumber(1.2, 1)->isInteger());
        self::assertFalse(Decimal::fromNumber('123.456789123456798123456789123456798')->isInteger());
    }

    public function testAdd()
    {
        self::assertSame('12.48', Decimal::fromNumber('7.4')->add(5.08)->toString());
        self::assertSame('0', Decimal::fromNumber(123456789)->add(-123456789)->toString());
        self::assertSame('133330337912761294108444769',
            Decimal::fromNumber('123456789123456789123456789')->add('9873548789304504984987980')->toString()
        );
        self::assertSame('-736.00', Decimal::zero()->add(Decimal::fromNumber('-736.0', 2))->toString());

        self::assertTrue(Decimal::fromNumber(7.4)->add(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->add(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->add(Decimal::createNaN())->isNaN());
    }

    public function testSubtract()
    {
        self::assertSame('12.48', Decimal::fromNumber('30')->subtract(17.52)->toString());
        self::assertSame('0', Decimal::fromNumber(123456789)->subtract(123456789)->toString());
        self::assertSame('113583240334152284138468809',
            Decimal::fromNumber('123456789123456789123456789')->subtract('9873548789304504984987980')->toString()
        );
        self::assertSame('-736.00', Decimal::zero()->subtract(Decimal::fromNumber('736.0', 2))->toString());

        self::assertTrue(Decimal::fromNumber(7.4)->subtract(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->subtract(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->subtract(Decimal::createNaN())->isNaN());
    }

    public function testMultiply()
    {
        self::assertSame('12', Decimal::fromNumber(3)->multiply(4)->toString());
        self::assertSame('12.000', Decimal::fromNumber('3.0')->multiply('4.00')->toString());
        self::assertSame('0', Decimal::zero()->multiply('123456789123456789123456789')->toString());
        self::assertSame('-73169.6487562430', Decimal::fromNumber('-74.5497890')->multiply(981.487)->toString());

        self::assertTrue(Decimal::fromNumber(7.4)->multiply(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->multiply(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->multiply(Decimal::createNaN())->isNaN());
    }

    public function testDivide()
    {
        self::assertSame('3.0000000000000000', Decimal::fromNumber(12)->divide(4)->toString());
        self::assertSame('-0.0759559617193096', Decimal::fromNumber('-74.5497890')->divide(981.487)->toString());
        self::assertSame('0.0000000000000000', Decimal::zero()->divide(42.57)->toString());

        try {
            Decimal::fromNumber(1)->divide(0);
            self::fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        self::assertTrue(Decimal::fromNumber(7.4)->divide(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->divide(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->divide(Decimal::createNaN())->isNaN());
    }

    public function testMod()
    {
        self::assertSame('1', Decimal::fromNumber(57)->mod(4)->toString());
        self::assertSame('0', Decimal::fromNumber(12)->mod(4)->toString());
        self::assertSame('-11.6997890', Decimal::fromNumber('-74.5497890')->mod(12.57)->toString());
        self::assertSame('0.00', Decimal::zero()->mod(42.57)->toString());

        try {
            Decimal::fromNumber(1)->mod(0);
            self::fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        self::assertTrue(Decimal::fromNumber(7.4)->mod(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->mod(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->mod(Decimal::createNaN())->isNaN());
    }

    public function testPow()
    {
        self::assertSame('10556001.0000000000000000', Decimal::fromNumber(57)->pow(4)->toString());
        self::assertSame(
            '-2196865363902893118253653.9788273777733597',
            Decimal::fromNumber('-74.5497890')->pow(13)->toString()
        );
        self::assertSame(
            '344073703240820000000000' /* '344073703240822219557582.4422472'*/,
            Decimal::fromNumber('74.5497890')->pow(12.57)->toString()
        );
        self::assertSame('0', Decimal::zero()->pow(42.57)->toString());
        self::assertSame('1.0000000000000000', Decimal::fromNumber(1579.487)->pow(0)->toString());

        try {
            Decimal::fromNumber('-74.5497890')->pow(12.57);
            self::fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        self::assertTrue(Decimal::fromNumber(7.4)->pow(Decimal::createNaN())->isNaN());
        self::assertTrue(Decimal::createNaN()->pow(7.4)->isNaN());
        self::assertTrue(Decimal::createNaN()->pow(Decimal::createNaN())->isNaN());
    }

    public function testAbs()
    {
        self::assertTrue(Decimal::createNaN()->abs()->isNaN());
        self::assertSame('123.00', Decimal::fromNumber('123.00')->abs()->toString());
        self::assertSame('123.00', Decimal::fromNumber('-123.00')->abs()->toString());
        self::assertSame('0', Decimal::zero()->abs()->toString());
        self::assertSame('0.000', Decimal::fromNumber('-0.000')->abs()->toString());
        self::assertSame('123456.789123456', Decimal::fromNumber('123456.789123456')->abs()->toString());
    }

    public function testNegate()
    {
        self::assertTrue(Decimal::createNaN()->negate()->isNaN());
        self::assertSame('-123.00', Decimal::fromNumber('123.00')->negate()->toString());
        self::assertSame('123.00', Decimal::fromNumber('-123.00')->negate()->toString());
        self::assertSame('0', Decimal::zero()->negate()->toString());
        self::assertSame('0.000', Decimal::fromNumber('-0.000')->negate()->toString());
        self::assertSame('-123456.789123456', Decimal::fromNumber('123456.789123456')->negate()->toString());
    }

    public function testFactorial()
    {
        self::assertSame('24', Decimal::fromNumber(4)->factorial()->toString());
        self::assertSame('1', Decimal::fromNumber(1)->factorial()->toString());
        self::assertSame('1', Decimal::fromNumber(0)->factorial()->toString());
        self::assertSame('1', Decimal::fromNumber(-987)->factorial()->toString());
        self::assertSame(
            '51084981466469576881306176261004598750272741624636207875758364885679783886389114119904367398214909451616' .
            '86595979719008559595721606020108179086356274071139240840260616228442434792644416829377030645987742962054' .
            '99801216218800688121199228255656037500367936574284764985773168878906892848844644235224691629246544199454' .
            '96940052746066950867784084753581540148194316888303839694860870357008235525028115281402379270279446743097' .
            '86889618056790145287203173419505643257656875434652825856988352685982672773583865408224672175181965805269' .
            '23962706113480130137867393202297060099407810255860388094930139921110304324733215322285896361507226213603' .
            '66978607484692870955691740723349227220367512994355146567475980006373400215826077949494335370591623671142' .
            '02695792393766922477161716795935965043996639267307318013937656307370656220077124129171082813207892867269' .
            '33776052806983409765126226862071752591089842539799702693305919514002658689440140017406063982207098594617' .
            '09972092316953639707607509036387468655214963966625322700932867195641466506305265122238332824677892386098' .
            '87304547794657047561447073568101153776293006833322975346131117569005319027621721593812222925401166331953' .
            '56685622882768145665362541399443274469237496751568383992586552271141810671813000311912984890766801729831' .
            '18121156086627360397334232174932132686080901569496392129263706595509472541921027039947595787992209537069' .
            '03137951711298580427641271949133473024776287626075356019901242436021186246604751118479715973171433036825' .
            '11923078521677576152006116690095756300755816322008970191101657384892882348458014135420900869263817566422' .
            '28872729319587724120647133695447658709466047131787467521648967375146176025775545958018149895570817463048' .
            '96832969281200399610594481253848429168907572184988979764755485483405013259231750386142207807793284139625' .
            '07723058923783049604210248458150479282296693428182189602435794731809869968834861646135862246777824053636' .
            '75732940386436560159992961462550218529921214223556288943276860000631422449845365510986932611414112386178' .
            '57344713423616450241034625451642181282535015238390792529919937109390239312631759033734037119928838060369' .
            '45170356626658272873520235631287564025160817497053257051964777693153111640297330674192821352142326056078' .
            '89159739038923579732630816548135472123812968829466513428484683760888731900685205308016495533252055718190' .
            '14264432000968303267716360974461462973063145489816746296626538787172558008351456562371927063568366226866' .
            '33339990298834293314628728489952297141157090239737711264689138736480615312234287495762670790845346569235' .
            '14931496743842559669386638509884709307166187205161445819828263679270112614012378542273837296427044021252' .
            '07786370696351448621818380649186879117478542450633781055045306389786628112706020086675401118190680987037' .
            '20329533545286990940961451209978420751090578592261208441764541753937812540043820913509941019594065901754' .
            '02086698874583611581937347003423449521223245166665792257252160462357733000925232292157683100179557359793' .
            '92629800758837047406823032092198745997604260628356600515820257280000000000000000000000000000000000000000' .
            '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000' .
            '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000' .
            '000000000000000000000000000000000000000000000000000000000',
            Decimal::fromNumber(1234)->factorial()->toString()
        );

        self::assertTrue(Decimal::createNaN()->factorial()->isNaN());

        try {
            Decimal::fromNumber(1.1)->factorial();
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Decimal::fromNumber('4.00')->factorial();
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        self::assertSame('24', Decimal::fromNumber('4.00')->round()->factorial()->toString());
    }

    public function testRound()
    {
        self::assertSame('123', Decimal::fromNumber('123.456')->round()->toString());
        self::assertSame('123.5', Decimal::fromNumber('123.456')->round(1)->toString());
        self::assertSame('123.46', Decimal::fromNumber('123.456')->round(2)->toString());
        self::assertSame('123.456', Decimal::fromNumber('123.456')->round(3)->toString());
        self::assertSame('123.4560', Decimal::fromNumber('123.456')->round(4)->toString());
        self::assertSame('100', Decimal::fromNumber('123.456')->round(-2)->toString());
        self::assertSame('0', Decimal::fromNumber('123.456')->round(-3)->toString());
        self::assertSame('0', Decimal::fromNumber('123.456')->round(-4)->toString());

        self::assertSame('0', Decimal::zero()->round()->toString());
        self::assertSame('0.0', Decimal::zero()->round(1)->toString());
        self::assertSame('0', Decimal::zero()->round(-1)->toString());

        self::assertSame('-1', Decimal::fromNumber('-1.45')->round()->toString());
        self::assertSame('-1.5', Decimal::fromNumber('-1.45')->round(1)->toString());
        self::assertSame('-1.45', Decimal::fromNumber('-1.45')->round(2)->toString());
        self::assertSame('0', Decimal::fromNumber('-1.45')->round(-1)->toString());

        self::assertTrue(Decimal::createNaN()->round()->isNaN());
        self::assertTrue(Decimal::createNaN()->round(2)->isNaN());
        self::assertTrue(Decimal::createNaN()->round(-3)->isNaN());
    }

    public function testFloor()
    {
        self::assertSame('123', Decimal::fromNumber('123.456')->floor()->toString());
        self::assertSame('123', Decimal::fromNumber('123.00')->floor()->toString());
        self::assertSame('123', Decimal::fromNumber('123')->floor()->toString());
        self::assertSame('0', Decimal::fromNumber('0.0000000000000000000000001')->floor()->toString());
        self::assertSame('0', Decimal::zero()->floor()->toString());
        self::assertSame('-1', Decimal::fromNumber('-0.0000000000000000000000001')->floor()->toString());
        self::assertSame('-2', Decimal::fromNumber('-1.3')->floor()->toString());

        self::assertTrue(Decimal::createNaN()->floor()->isNaN());
    }

    public function testCeil()
    {
        self::assertSame('124', Decimal::fromNumber('123.456')->ceil()->toString());
        self::assertSame('123', Decimal::fromNumber('123.00')->ceil()->toString());
        self::assertSame('123', Decimal::fromNumber('123')->ceil()->toString());
        self::assertSame('1', Decimal::fromNumber('0.0000000000000000000000001')->ceil()->toString());
        self::assertSame('0', Decimal::zero()->ceil()->toString());
        self::assertSame('0', Decimal::fromNumber('-0.0000000000000000000000001')->ceil()->toString());
        self::assertSame('-1', Decimal::fromNumber('-1.3')->ceil()->toString());

        self::assertTrue(Decimal::createNaN()->ceil()->isNaN());
    }

    public function testMax()
    {
        $a = Decimal::fromNumber(12);
        self::assertSame($a, $a->max(11.9));
        self::assertSame($a, Decimal::fromNumber(11.9)->max($a));

        $b = Decimal::fromNumber(12);
        self::assertSame($b, $b->max(12));
        self::assertNotSame($b, Decimal::fromNumber(12)->max($b));
        self::assertTrue(Decimal::fromNumber(12)->max($b)->equals(12));

        $m = Decimal::createNaN();
        self::assertSame($m, $m->max(Decimal::createNaN()));
        self::assertSame($m, $m->max(Decimal::fromNumber('123456789123456789123456798')));
        self::assertSame($m, Decimal::fromNumber(123)->max($m));
    }

    public function testMin()
    {
        $a = Decimal::fromNumber(12);
        self::assertSame($a, $a->min(12.1));
        self::assertSame($a, Decimal::fromNumber(12.1)->min($a));

        $b = Decimal::fromNumber(12);
        self::assertSame($b, $b->min(12));
        self::assertNotSame($b, Decimal::fromNumber(12)->min($b));
        self::assertTrue(Decimal::fromNumber(12)->min($b)->equals(12));

        $m = Decimal::createNaN();
        $n = Decimal::fromNumber('123456789123456789123456798');
        self::assertSame($m, $m->min(Decimal::createNaN()));
        self::assertSame($n, $m->min($n));
        self::assertTrue(Decimal::fromNumber(123)->min($m)->equals(123));
    }

    public function testSqrt()
    {
        self::assertTrue(Decimal::createNaN()->sqrt()->isNaN());
        self::assertSame('4.0000000000000000', Decimal::fromNumber(16)->sqrt()->toString());
        self::assertSame('4.2000000000000000', Decimal::fromNumber(17.64)->sqrt()->toString());
        self::assertSame('4.1231056256176605', Decimal::fromNumber(17)->sqrt()->toString());
        self::assertSame('0.0000000000000000', Decimal::fromNumber(0)->sqrt()->toString());

        try {
            Decimal::fromNumber(-9)->sqrt();
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testToInt()
    {
        self::assertSame(123, Decimal::fromNumber(123)->toInt());
        self::assertSame(-123456, Decimal::fromNumber('-123456')->toInt());
        self::assertSame(0, Decimal::zero()->toInt());
        self::assertSame(12, Decimal::fromNumber(12.945)->toInt());
        self::assertSame(-12, Decimal::fromNumber(-12.5987)->toInt());
        self::assertSame(PHP_INT_MAX, Decimal::fromNumber('123456789123456789123456789123456789123456798')->toInt());
        self::assertNull(Decimal::createNaN()->toInt());
    }

    public function testToFloat()
    {
        self::assertSame(123.0, Decimal::fromNumber(123)->toFloat());
        self::assertSame(-123456.0, Decimal::fromNumber('-123456')->toFloat());
        self::assertSame(0.0, Decimal::zero()->toFloat());
        self::assertSame(12.345, Decimal::fromNumber(12.345)->toFloat());
        self::assertTrue(is_nan(Decimal::createNaN()->toFloat()));
    }

    public function testToString()
    {
        self::assertSame('123', Decimal::fromNumber(123)->toString());
        self::assertSame('-123456', Decimal::fromNumber('-123456')->toString());
        self::assertSame('0', Decimal::fromNumber('00')->toString());
        self::assertSame('12.345', Decimal::fromNumber(12.345)->toString());
        self::assertSame('NaN', Decimal::createNaN()->toString());

        self::assertSame('123', (string)Decimal::fromNumber(123));
    }

    /**
     * @requires extension gmp
     */
    public function testToGmp()
    {
        self::assertSame('123', gmp_strval(Decimal::fromNumber(123)->toGMP()));
        self::assertSame('-123456', gmp_strval(Decimal::fromNumber('-123456')->toGMP()));
        self::assertSame('0', gmp_strval(Decimal::zero()->toGMP()));

        try {
            Decimal::createNaN()->toGMP();
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Decimal::fromNumber(12.3)->toGMP();
            self::fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }
}
