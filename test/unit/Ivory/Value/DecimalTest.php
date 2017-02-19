<?php
namespace Ivory\Value;

use Ivory\Exception\UndefinedOperationException;

class DecimalTest extends \PHPUnit\Framework\TestCase
{
    public function testEquals()
    {
        $this->assertEquals(Decimal::fromNumber('1'), Decimal::fromNumber(1));
        $this->assertTrue(Decimal::fromNumber('1')->equals(1));
        $this->assertEquals(0, Decimal::fromNumber('1')->compareTo(1));
        $this->assertFalse(Decimal::fromNumber('1')->lessThan(1));
        $this->assertFalse(Decimal::fromNumber('1')->greaterThan(1));
        $this->assertNotEquals(Decimal::fromNumber('1'), Decimal::fromNumber(2));
        $this->assertFalse(Decimal::fromNumber('1')->equals(2));
        $this->assertEquals(-1, Decimal::fromNumber('1')->compareTo(2));
        $this->assertTrue(Decimal::fromNumber('1')->lessThan(2));
        $this->assertFalse(Decimal::fromNumber('1')->greaterThan(2));

        $this->assertEquals(Decimal::zero(), Decimal::fromNumber(0));
        $this->assertTrue(Decimal::zero()->equals(0));
        $this->assertEquals(0, Decimal::zero()->compareTo(0));
        $this->assertFalse(Decimal::zero()->lessThan(0));
        $this->assertFalse(Decimal::zero()->greaterThan(0));
        $this->assertNotEquals(Decimal::zero(), Decimal::fromNumber(0.1));
        $this->assertFalse(Decimal::zero()->equals(0.1));

        $this->assertEquals(Decimal::fromNumber('123.456'), Decimal::fromNumber(123456e-3));
        $this->assertTrue(Decimal::fromNumber('123.456')->equals(123456e-3));
        $this->assertEquals(0, Decimal::fromNumber('123.456')->compareTo(123456e-3));
        $this->assertFalse(Decimal::fromNumber('123.456')->lessThan(123456e-3));
        $this->assertFalse(Decimal::fromNumber('123.456')->greaterThan(123456e-3));
        $this->assertNotEquals(Decimal::fromNumber('123.456'), Decimal::fromNumber(123457e-3));
        $this->assertFalse(Decimal::fromNumber('123.456')->equals(123457e-3));
        $this->assertEquals(-1, Decimal::fromNumber('123.456')->compareTo(123457e-3));
        $this->assertTrue(Decimal::fromNumber('123.456')->lessThan(123457e-3));
        $this->assertFalse(Decimal::fromNumber('123.456')->greaterThan(123457e-3));
        $this->assertEquals(1, Decimal::fromNumber('123.457')->compareTo(123456e-3));
        $this->assertFalse(Decimal::fromNumber('123.457')->lessThan(123456e-3));
        $this->assertTrue(Decimal::fromNumber('123.457')->greaterThan(123456e-3));

        $this->assertEquals(Decimal::NaN(), Decimal::NaN());
        $this->assertNotEquals(Decimal::NaN(), Decimal::fromNumber(0));
        $this->assertNotEquals(Decimal::NaN(), Decimal::fromNumber(1));
        $this->assertNotEquals(Decimal::fromNumber(0), Decimal::NaN());
        $this->assertNotEquals(Decimal::fromNumber(1), Decimal::NaN());
        $this->assertTrue(Decimal::NaN()->equals(Decimal::NaN()));
        $this->assertFalse(Decimal::NaN()->equals(0));
        $this->assertFalse(Decimal::NaN()->equals(1));
        $this->assertEquals(0, Decimal::NaN()->compareTo(Decimal::NaN()));
        $this->assertTrue(Decimal::NaN()->greaterThan(0));
        $this->assertFalse(Decimal::fromNumber(0)->greaterThan(Decimal::NaN()));
        $this->assertTrue(Decimal::fromNumber(0)->lessThan(Decimal::NaN()));
        $this->assertFalse(Decimal::NaN()->lessThan(0));

        $this->assertEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234, 2));
        $this->assertNotEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234));
        $this->assertNotEquals(Decimal::fromNumber('1234.00'), Decimal::fromNumber(1234.00));
        $this->assertTrue(Decimal::fromNumber('1234.00')->equals(Decimal::fromNumber(1234, 2)));
        $this->assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(Decimal::fromNumber(1234, 2)));
        $this->assertTrue(Decimal::fromNumber('1234.00')->equals(1234));
        $this->assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(1234));
        $this->assertTrue(Decimal::fromNumber('1234.00')->equals(1234.00));
        $this->assertEquals(0, Decimal::fromNumber('1234.00')->compareTo(1234.00));

        $this->assertEquals(Decimal::fromNumber('123.95', 1), Decimal::fromNumber('124.0'));
        $this->assertNotEquals(Decimal::fromNumber('123.95', 2), Decimal::fromNumber('124.00'));
        $this->assertTrue(Decimal::fromNumber('123.95', 1)->equals('124.0'));
        $this->assertFalse(Decimal::fromNumber('123.95', 2)->equals('124.00'));

        $this->assertEquals(Decimal::fromNumber(-5, 2), Decimal::fromNumber('-5.00'));
        $this->assertNotEquals(Decimal::fromNumber(-5, 2), Decimal::fromNumber('-5'));
        $this->assertTrue(Decimal::fromNumber(-5, 2)->equals(Decimal::fromNumber('-5.00')));
        $this->assertTrue(Decimal::fromNumber(-5, 2)->equals(Decimal::fromNumber('-5')));
    }

    /**
     * @requires extension gmp
     */
    public function testEqualsGmp()
    {
        $this->assertEquals(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890')),
            Decimal::fromNumber('123456789012345678901234567890')
        );
        $this->assertTrue(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->equals('123456789012345678901234567890')
        );
        $this->assertEquals(
            0,
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->compareTo('123456789012345678901234567890')
        );
        $this->assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->lessThan('123456789012345678901234567890')
        );
        $this->assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->greaterThan('123456789012345678901234567890')
        );
        $this->assertNotEquals(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890')),
            Decimal::fromNumber('123456789012345678901234567891')
        );
        $this->assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->equals('123456789012345678901234567891')
        );
        $this->assertEquals(
            -1,
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->compareTo('123456789012345678901234567891')
        );
        $this->assertTrue(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->lessThan('123456789012345678901234567891')
        );
        $this->assertFalse(
            Decimal::fromNumber(gmp_init('123456789012345678901234567890'))
                ->greaterThan('123456789012345678901234567891')
        );
    }

    public function testFromNumber()
    {
        $this->assertSame('1.5', Decimal::fromNumber('1.49', 1)->toString());
        $this->assertSame('1.5', Decimal::fromNumber('1.45', 1)->toString());
        $this->assertSame('1.4', Decimal::fromNumber('1.4499999999999999999999999999999999999999999', 1)->toString());
        $this->assertSame(
            '1.450000000000000000000000000000000000000000',
            Decimal::fromNumber('1.4499999999999999999999999999999999999999999', 42)->toString()
        );

        $this->assertSame('0', Decimal::fromNumber(0.1, 0)->toString());
        $this->assertSame('0', Decimal::fromNumber(0)->toString());
        $this->assertSame('0', Decimal::fromNumber(-.4999, 0)->toString());
        $this->assertSame('-1', Decimal::fromNumber(-.5, 0)->toString());

        $this->assertSame('1.420000', Decimal::fromNumber(1.42, 6)->toString());

        $this->assertSame('0', Decimal::fromNumber(-0)->toString());
        $this->assertSame('0.12', Decimal::fromNumber('.12')->toString());
        $this->assertSame('-0.12', Decimal::fromNumber('-.12')->toString());

        $this->assertSame(
            '123456789123456789123456789',
            Decimal::fromNumber(Decimal::fromNumber('123456789123456789123456789'))->toString()
        );
        $obj = new class {
            public function __toString()
            {
                return '    0042    ';
            }
        };
        $this->assertSame('42', Decimal::fromNumber($obj)->toString());

        $this->assertSame('123400000000000000000000000', Decimal::fromNumber(12.34e25)->toString());
        $this->assertSame('1234.56', Decimal::fromNumber(123.456e1)->toString());
        $this->assertSame('1.23456', Decimal::fromNumber(123.456e-2)->toString());
        $this->assertSame('-0.000000000000000000000001234', Decimal::fromNumber(-12.34e-25)->toString());

        $this->assertSame(0, Decimal::fromNumber('1234')->getScale());
        $this->assertSame(0, Decimal::fromNumber('1234.02', 0)->getScale());
        $this->assertSame(2, Decimal::fromNumber('1234.02')->getScale());
        $this->assertSame(2, Decimal::fromNumber('1234.00')->getScale());
        $this->assertSame('1234.00', Decimal::fromNumber('1234.00')->toString());
        $this->assertSame(42, Decimal::fromNumber('1234.00', 42)->getScale());
        $this->assertSame(2, Decimal::fromNumber('-123.45')->getScale());
        $this->assertSame(60,
            Decimal::fromNumber('.123456789012345678901234567890123456789012345678901234567890')->getScale()
        );

        $this->assertSame('1234', Decimal::fromNumber('01234   ')->toString());
        $this->assertSame('0.00', Decimal::fromNumber('00.00')->toString());
        $this->assertSame('0.0', Decimal::fromNumber('0.0')->toString());
        $this->assertSame('0', Decimal::fromNumber('   00')->toString());
        $this->assertSame('-1234', Decimal::fromNumber('  -01234 ')->toString());
        $this->assertSame('0', Decimal::fromNumber('-0')->toString());
        $this->assertSame('0', Decimal::fromNumber('-00')->toString());
        $this->assertSame('0.00', Decimal::fromNumber(' -00.00  ')->toString());
        $this->assertSame('0.0', Decimal::fromNumber('-0.0')->toString());

        try {
            Decimal::fromNumber(null);
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Decimal::fromNumber('123456A7');
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            Decimal::fromNumber(42.3, -1);
            $this->fail('\InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * @requires extension gmp
     */
    public function testFromNumberGmp()
    {
        $this->assertSame(
            '123456789123456789123456789',
            Decimal::fromNumber(gmp_init('123456789123456789123456789'))->toString()
        );
    }

    public function testNan()
    {
        $this->assertSame('NaN', Decimal::NaN()->toString());
        $this->assertNull(Decimal::NaN()->getScale());

        $this->assertTrue(Decimal::NaN()->isNaN());
        $this->assertFalse(Decimal::zero()->isNaN());
    }

    public function testZero()
    {
        $this->assertSame('0', Decimal::zero()->toString());
        $this->assertSame(0, Decimal::zero()->getScale());

        $this->assertTrue(Decimal::zero()->isZero());
        $this->assertTrue(Decimal::fromNumber(0)->isZero());
        $this->assertTrue(Decimal::fromNumber(0.1, 0)->isZero());
        $this->assertFalse(Decimal::NaN()->isZero());
        $this->assertFalse(Decimal::fromNumber(1)->isZero());
        $this->assertFalse(Decimal::fromNumber(-0.5)->isZero());
    }

    public function testIsPositive()
    {
        $this->assertTrue(Decimal::fromNumber(0.1)->isPositive());
        $this->assertFalse(Decimal::fromNumber(0.1, 0)->isPositive());
        $this->assertFalse(Decimal::zero()->isPositive());
        $this->assertFalse(Decimal::fromNumber(-0.1)->isPositive());
        $this->assertFalse(Decimal::NaN()->isPositive());
    }

    public function testIsNegative()
    {
        $this->assertTrue(Decimal::fromNumber(-0.1)->isNegative());
        $this->assertFalse(Decimal::fromNumber(-0.1, 0)->isNegative());
        $this->assertFalse(Decimal::zero()->isNegative());
        $this->assertFalse(Decimal::fromNumber(0.1)->isNegative());
        $this->assertFalse(Decimal::NaN()->isNegative());
    }

    public function testIsInteger()
    {
        $this->assertTrue(Decimal::zero()->isInteger());
        $this->assertTrue(Decimal::fromNumber(12)->isInteger());
        $this->assertTrue(Decimal::fromNumber(-1)->isInteger());
        $this->assertTrue(Decimal::fromNumber(1.2, 0)->isInteger());
        $this->assertTrue(Decimal::fromNumber('123456789123456789123456798')->isInteger());

        $this->assertFalse(Decimal::NaN()->isInteger());
        $this->assertFalse(Decimal::fromNumber(1.2, 1)->isInteger());
        $this->assertFalse(Decimal::fromNumber('123.456789123456798123456789123456798')->isInteger());
    }

    public function testAdd()
    {
        $this->assertSame('12.48', Decimal::fromNumber('7.4')->add(5.08)->toString());
        $this->assertSame('0', Decimal::fromNumber(123456789)->add(-123456789)->toString());
        $this->assertSame('133330337912761294108444769',
            Decimal::fromNumber('123456789123456789123456789')->add('9873548789304504984987980')->toString()
        );
        $this->assertSame('-736.00', Decimal::zero()->add(Decimal::fromNumber('-736.0', 2))->toString());

        $this->assertTrue(Decimal::fromNumber(7.4)->add(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->add(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->add(Decimal::NaN())->isNaN());
    }

    public function testSubtract()
    {
        $this->assertSame('12.48', Decimal::fromNumber('30')->subtract(17.52)->toString());
        $this->assertSame('0', Decimal::fromNumber(123456789)->subtract(123456789)->toString());
        $this->assertSame('113583240334152284138468809',
            Decimal::fromNumber('123456789123456789123456789')->subtract('9873548789304504984987980')->toString()
        );
        $this->assertSame('-736.00', Decimal::zero()->subtract(Decimal::fromNumber('736.0', 2))->toString());

        $this->assertTrue(Decimal::fromNumber(7.4)->subtract(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->subtract(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->subtract(Decimal::NaN())->isNaN());
    }

    public function testMultiply()
    {
        $this->assertSame('12', Decimal::fromNumber(3)->multiply(4)->toString());
        $this->assertSame('12.000', Decimal::fromNumber('3.0')->multiply('4.00')->toString());
        $this->assertSame('0', Decimal::zero()->multiply('123456789123456789123456789')->toString());
        $this->assertSame('-73169.6487562430', Decimal::fromNumber('-74.5497890')->multiply(981.487)->toString());

        $this->assertTrue(Decimal::fromNumber(7.4)->multiply(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->multiply(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->multiply(Decimal::NaN())->isNaN());
    }

    public function testDivide()
    {
        $this->assertSame('3.0000000000000000', Decimal::fromNumber(12)->divide(4)->toString());
        $this->assertSame('-0.0759559617193096', Decimal::fromNumber('-74.5497890')->divide(981.487)->toString());
        $this->assertSame('0.0000000000000000', Decimal::zero()->divide(42.57)->toString());

        try {
            Decimal::fromNumber(1)->divide(0);
            $this->fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        $this->assertTrue(Decimal::fromNumber(7.4)->divide(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->divide(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->divide(Decimal::NaN())->isNaN());
    }

    public function testMod()
    {
        $this->assertSame('1', Decimal::fromNumber(57)->mod(4)->toString());
        $this->assertSame('0', Decimal::fromNumber(12)->mod(4)->toString());
        $this->assertSame('-11.6997890', Decimal::fromNumber('-74.5497890')->mod(12.57)->toString());
        $this->assertSame('0.00', Decimal::zero()->mod(42.57)->toString());

        try {
            Decimal::fromNumber(1)->mod(0);
            $this->fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        $this->assertTrue(Decimal::fromNumber(7.4)->mod(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->mod(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->mod(Decimal::NaN())->isNaN());
    }

    public function testPow()
    {
        $this->assertSame('10556001.0000000000000000', Decimal::fromNumber(57)->pow(4)->toString());
        $this->assertSame(
            '-2196865363902893118253653.9788273777733597',
            Decimal::fromNumber('-74.5497890')->pow(13)->toString()
        );
        $this->assertSame(
            '344073703240820000000000' /* '344073703240822219557582.4422472'*/,
            Decimal::fromNumber('74.5497890')->pow(12.57)->toString()
        );
        $this->assertSame('0', Decimal::zero()->pow(42.57)->toString());
        $this->assertSame('1.0000000000000000', Decimal::fromNumber(1579.487)->pow(0)->toString());

        try {
            Decimal::fromNumber('-74.5497890')->pow(12.57);
            $this->fail('\RuntimeException expected');
        } catch (\RuntimeException $e) {
        }

        $this->assertTrue(Decimal::fromNumber(7.4)->pow(Decimal::NaN())->isNaN());
        $this->assertTrue(Decimal::NaN()->pow(7.4)->isNaN());
        $this->assertTrue(Decimal::NaN()->pow(Decimal::NaN())->isNaN());
    }

    public function testAbs()
    {
        $this->assertTrue(Decimal::NaN()->abs()->isNaN());
        $this->assertSame('123.00', Decimal::fromNumber('123.00')->abs()->toString());
        $this->assertSame('123.00', Decimal::fromNumber('-123.00')->abs()->toString());
        $this->assertSame('0', Decimal::zero()->abs()->toString());
        $this->assertSame('0.000', Decimal::fromNumber('-0.000')->abs()->toString());
        $this->assertSame('123456.789123456', Decimal::fromNumber('123456.789123456')->abs()->toString());
    }

    public function testNegate()
    {
        $this->assertTrue(Decimal::NaN()->negate()->isNaN());
        $this->assertSame('-123.00', Decimal::fromNumber('123.00')->negate()->toString());
        $this->assertSame('123.00', Decimal::fromNumber('-123.00')->negate()->toString());
        $this->assertSame('0', Decimal::zero()->negate()->toString());
        $this->assertSame('0.000', Decimal::fromNumber('-0.000')->negate()->toString());
        $this->assertSame('-123456.789123456', Decimal::fromNumber('123456.789123456')->negate()->toString());
    }

    public function testFactorial()
    {
        $this->assertSame('24', Decimal::fromNumber(4)->factorial()->toString());
        $this->assertSame('1', Decimal::fromNumber(1)->factorial()->toString());
        $this->assertSame('1', Decimal::fromNumber(0)->factorial()->toString());
        $this->assertSame('1', Decimal::fromNumber(-987)->factorial()->toString());
        $this->assertSame(
            '51084981466469576881306176261004598750272741624636207875758364885679783886389114119904367398214909451616865959797190085595957216060201081790863562740711392408402606162284424347926444168293770306459877429620549980121621880068812119922825565603750036793657428476498577316887890689284884464423522469162924654419945496940052746066950867784084753581540148194316888303839694860870357008235525028115281402379270279446743097868896180567901452872031734195056432576568754346528258569883526859826727735838654082246721751819658052692396270611348013013786739320229706009940781025586038809493013992111030432473321532228589636150722621360366978607484692870955691740723349227220367512994355146567475980006373400215826077949494335370591623671142026957923937669224771617167959359650439966392673073180139376563073706562200771241291710828132078928672693377605280698340976512622686207175259108984253979970269330591951400265868944014001740606398220709859461709972092316953639707607509036387468655214963966625322700932867195641466506305265122238332824677892386098873045477946570475614470735681011537762930068333229753461311175690053190276217215938122229254011663319535668562288276814566536254139944327446923749675156838399258655227114181067181300031191298489076680172983118121156086627360397334232174932132686080901569496392129263706595509472541921027039947595787992209537069031379517112985804276412719491334730247762876260753560199012424360211862466047511184797159731714330368251192307852167757615200611669009575630075581632200897019110165738489288234845801413542090086926381756642228872729319587724120647133695447658709466047131787467521648967375146176025775545958018149895570817463048968329692812003996105944812538484291689075721849889797647554854834050132592317503861422078077932841396250772305892378304960421024845815047928229669342818218960243579473180986996883486164613586224677782405363675732940386436560159992961462550218529921214223556288943276860000631422449845365510986932611414112386178573447134236164502410346254516421812825350152383907925299199371093902393126317590337340371199288380603694517035662665827287352023563128756402516081749705325705196477769315311164029733067419282135214232605607889159739038923579732630816548135472123812968829466513428484683760888731900685205308016495533252055718190142644320009683032677163609744614629730631454898167462966265387871725580083514565623719270635683662268663333999029883429331462872848995229714115709023973771126468913873648061531223428749576267079084534656923514931496743842559669386638509884709307166187205161445819828263679270112614012378542273837296427044021252077863706963514486218183806491868791174785424506337810550453063897866281127060200866754011181906809870372032953354528699094096145120997842075109057859226120844176454175393781254004382091350994101959406590175402086698874583611581937347003423449521223245166665792257252160462357733000925232292157683100179557359793926298007588370474068230320921987459976042606283566005158202572800000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',
            Decimal::fromNumber(1234)->factorial()->toString()
        );

        $this->assertTrue(Decimal::NaN()->factorial()->isNaN());

        try {
            Decimal::fromNumber(1.1)->factorial();
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Decimal::fromNumber('4.00')->factorial();
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        $this->assertSame('24', Decimal::fromNumber('4.00')->round()->factorial()->toString());
    }

    public function testRound()
    {
        $this->assertSame('123', Decimal::fromNumber('123.456')->round()->toString());
        $this->assertSame('123.5', Decimal::fromNumber('123.456')->round(1)->toString());
        $this->assertSame('123.46', Decimal::fromNumber('123.456')->round(2)->toString());
        $this->assertSame('123.456', Decimal::fromNumber('123.456')->round(3)->toString());
        $this->assertSame('123.4560', Decimal::fromNumber('123.456')->round(4)->toString());
        $this->assertSame('100', Decimal::fromNumber('123.456')->round(-2)->toString());
        $this->assertSame('0', Decimal::fromNumber('123.456')->round(-3)->toString());
        $this->assertSame('0', Decimal::fromNumber('123.456')->round(-4)->toString());

        $this->assertSame('0', Decimal::zero()->round()->toString());
        $this->assertSame('0.0', Decimal::zero()->round(1)->toString());
        $this->assertSame('0', Decimal::zero()->round(-1)->toString());

        $this->assertSame('-1', Decimal::fromNumber('-1.45')->round()->toString());
        $this->assertSame('-1.5', Decimal::fromNumber('-1.45')->round(1)->toString());
        $this->assertSame('-1.45', Decimal::fromNumber('-1.45')->round(2)->toString());
        $this->assertSame('0', Decimal::fromNumber('-1.45')->round(-1)->toString());

        $this->assertTrue(Decimal::NaN()->round()->isNaN());
        $this->assertTrue(Decimal::NaN()->round(2)->isNaN());
        $this->assertTrue(Decimal::NaN()->round(-3)->isNaN());
    }

    public function testFloor()
    {
        $this->assertSame('123', Decimal::fromNumber('123.456')->floor()->toString());
        $this->assertSame('123', Decimal::fromNumber('123.00')->floor()->toString());
        $this->assertSame('123', Decimal::fromNumber('123')->floor()->toString());
        $this->assertSame('0', Decimal::fromNumber('0.0000000000000000000000001')->floor()->toString());
        $this->assertSame('0', Decimal::zero()->floor()->toString());
        $this->assertSame('-1', Decimal::fromNumber('-0.0000000000000000000000001')->floor()->toString());
        $this->assertSame('-2', Decimal::fromNumber('-1.3')->floor()->toString());

        $this->assertTrue(Decimal::NaN()->floor()->isNaN());
    }

    public function testCeil()
    {
        $this->assertSame('124', Decimal::fromNumber('123.456')->ceil()->toString());
        $this->assertSame('123', Decimal::fromNumber('123.00')->ceil()->toString());
        $this->assertSame('123', Decimal::fromNumber('123')->ceil()->toString());
        $this->assertSame('1', Decimal::fromNumber('0.0000000000000000000000001')->ceil()->toString());
        $this->assertSame('0', Decimal::zero()->ceil()->toString());
        $this->assertSame('0', Decimal::fromNumber('-0.0000000000000000000000001')->ceil()->toString());
        $this->assertSame('-1', Decimal::fromNumber('-1.3')->ceil()->toString());

        $this->assertTrue(Decimal::NaN()->ceil()->isNaN());
    }

    public function testMax()
    {
        $a = Decimal::fromNumber(12);
        $this->assertSame($a, $a->max(11.9));
        $this->assertSame($a, Decimal::fromNumber(11.9)->max($a));

        $b = Decimal::fromNumber(12);
        $this->assertSame($b, $b->max(12));
        $this->assertNotSame($b, Decimal::fromNumber(12)->max($b));
        $this->assertTrue(Decimal::fromNumber(12)->max($b)->equals(12));

        $m = Decimal::NaN();
        $this->assertSame($m, $m->max(Decimal::NaN()));
        $this->assertSame($m, $m->max(Decimal::fromNumber('123456789123456789123456798')));
        $this->assertSame($m, Decimal::fromNumber(123)->max($m));
    }

    public function testMin()
    {
        $a = Decimal::fromNumber(12);
        $this->assertSame($a, $a->min(12.1));
        $this->assertSame($a, Decimal::fromNumber(12.1)->min($a));

        $b = Decimal::fromNumber(12);
        $this->assertSame($b, $b->min(12));
        $this->assertNotSame($b, Decimal::fromNumber(12)->min($b));
        $this->assertTrue(Decimal::fromNumber(12)->min($b)->equals(12));

        $m = Decimal::NaN();
        $n = Decimal::fromNumber('123456789123456789123456798');
        $this->assertSame($m, $m->min(Decimal::NaN()));
        $this->assertSame($n, $m->min($n));
        $this->assertTrue(Decimal::fromNumber(123)->min($m)->equals(123));
    }

    public function testSqrt()
    {
        $this->assertTrue(Decimal::NaN()->sqrt()->isNaN());
        $this->assertSame('4.00000000000000000', Decimal::fromNumber(16)->sqrt()->toString());
        $this->assertSame('4.20000000000000000', Decimal::fromNumber(17.64)->sqrt()->toString());
        $this->assertSame('4.12310562561766054', Decimal::fromNumber(17)->sqrt()->toString());
        $this->assertSame('0', Decimal::fromNumber(0)->sqrt()->toString());

        try {
            Decimal::fromNumber(-9)->sqrt();
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }

    public function testToInt()
    {
        $this->assertSame(123, Decimal::fromNumber(123)->toInt());
        $this->assertSame(-123456, Decimal::fromNumber('-123456')->toInt());
        $this->assertSame(0, Decimal::zero()->toInt());
        $this->assertSame(12, Decimal::fromNumber(12.945)->toInt());
        $this->assertSame(-12, Decimal::fromNumber(-12.5987)->toInt());
        $this->assertSame(PHP_INT_MAX, Decimal::fromNumber('123456789123456789123456789123456789123456798')->toInt());
        $this->assertNull(Decimal::NaN()->toInt());
    }

    public function testToFloat()
    {
        $this->assertSame(123.0, Decimal::fromNumber(123)->toFloat());
        $this->assertSame(-123456.0, Decimal::fromNumber('-123456')->toFloat());
        $this->assertSame(0.0, Decimal::zero()->toFloat());
        $this->assertSame(12.345, Decimal::fromNumber(12.345)->toFloat());
        $this->assertTrue(is_nan(Decimal::NaN()->toFloat()));
    }

    public function testToString()
    {
        $this->assertSame('123', Decimal::fromNumber(123)->toString());
        $this->assertSame('-123456', Decimal::fromNumber('-123456')->toString());
        $this->assertSame('0', Decimal::fromNumber('00')->toString());
        $this->assertSame('12.345', Decimal::fromNumber(12.345)->toString());
        $this->assertSame('NaN', Decimal::NaN()->toString());

        $this->assertSame('123', (string)Decimal::fromNumber(123));
    }

    /**
     * @requires extension gmp
     */
    public function testToGmp()
    {
        $this->assertSame('123', gmp_strval(Decimal::fromNumber(123)->toGMP()));
        $this->assertSame('-123456', gmp_strval(Decimal::fromNumber('-123456')->toGMP()));
        $this->assertSame('0', gmp_strval(Decimal::zero()->toGMP()));

        try {
            Decimal::NaN()->toGMP();
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }

        try {
            Decimal::fromNumber(12.3)->toGMP();
            $this->fail('UndefinedOperationException expected');
        } catch (UndefinedOperationException $e) {
        }
    }
}
