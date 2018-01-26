<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Connection\ITxHandle;
use Ivory\Exception\IncomparableException;
use Ivory\IvoryTestCase;
use Ivory\Value\EnumItem;
use Ivory\Value\Range;

class EnumTypeTest extends IvoryTestCase
{
    /** @var ITxHandle */
    private $tx;

    protected function setUp()
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();
        $this->tx = $conn->startTransaction();

        $conn->command(
            "CREATE TYPE sms_protocol AS ENUM ('SMPP', 'REST', 'Jupiter')"
        );
        $conn->command(
            "CREATE TYPE planet AS ENUM ('Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune')"
        );
        $conn->command(
            "CREATE TYPE planet_range AS RANGE (SUBTYPE = planet)"
        );
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->tx->rollback();
    }

    public function testReading()
    {
        $conn = $this->getIvoryConnection();
        $tuple = $conn->querySingleTuple(
            "SELECT
                 'SMPP'::sms_protocol AS sms_prot1,
                 'Jupiter'::sms_protocol AS sms_prot2,
                 'Mars'::planet AS planet1,
                 'Mars'::planet AS planet2,
                 'Jupiter'::planet AS planet3,
                 planet_range('Jupiter', 'Neptune') AS planet_rng"
        );

        assert($tuple->sms_prot1 instanceof EnumItem);
        assert($tuple->sms_prot2 instanceof EnumItem);
        assert($tuple->planet1 instanceof EnumItem);
        assert($tuple->planet2 instanceof EnumItem);
        assert($tuple->planet3 instanceof EnumItem);
        assert($tuple->planet_rng instanceof Range);

        $this->assertSame('SMPP', $tuple->sms_prot1->getValue());

        $this->assertTrue($tuple->planet1->equals($tuple->planet2));
        $this->assertFalse($tuple->planet1->equals($tuple->planet3));
        $this->assertFalse($tuple->sms_prot1->equals($tuple->planet1));
        $this->assertFalse($tuple->sms_prot2->equals($tuple->planet3));

        $this->assertEquals(0, $tuple->planet1->compareTo($tuple->planet2));
        $this->assertLessThan(0, $tuple->planet1->compareTo($tuple->planet3));
        try {
            $tuple->planet1->compareTo($tuple->sms_prot1);
            $this->fail(IncomparableException::class . ' was expected');
        } catch (IncomparableException $e) {
        }

        $this->assertSame('Jupiter', $tuple->planet_rng->getLower()->getValue());
        $this->assertSame('Neptune', $tuple->planet_rng->getUpper()->getValue());
    }

    public function testSerializing()
    {
        $conn = $this->getIvoryConnection();
        $this->assertTrue(
            $conn->querySingleValue(
                'SELECT %planet < %planet',
                'Venus',
                EnumItem::forType('public', 'planet', 'Uranus')
            )
        );
    }
}
