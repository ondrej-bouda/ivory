<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Connection\Connection;
use Ivory\Connection\ITxHandle;
use Ivory\Exception\IncomparableException;
use Ivory\IvoryTestCase;
use Ivory\Type\Postgresql\StrictEnumType;
use Ivory\Value\Range;

class CustomEnumTypeTest extends IvoryTestCase
{
    /** @var Connection */
    private $conn;
    /** @var ITxHandle */
    private $tx;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->tx = $this->conn->startTransaction();

        $this->conn->command(
            "CREATE TYPE sms_protocol AS ENUM ('SMPP', 'REST', 'Jupiter')"
        );
        $this->conn->command(
            "CREATE TYPE planet AS ENUM ('Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune')"
        );
        $this->conn->command(
            "CREATE TYPE planet_range AS RANGE (SUBTYPE = planet)"
        );

        $typeRegister = $this->conn->getTypeRegister();
        $typeRegister->registerType(new StrictEnumType('public', 'sms_protocol', SmsProtocol::class));
        $typeRegister->registerType(new StrictEnumType('public', 'planet', Planet::class));
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

        assert($tuple->sms_prot1 instanceof SmsProtocol);
        assert($tuple->sms_prot2 instanceof SmsProtocol);
        assert($tuple->planet1 instanceof Planet);
        assert($tuple->planet2 instanceof Planet);
        assert($tuple->planet3 instanceof Planet);
        assert($tuple->planet_rng instanceof Range);

        $this->assertTrue($tuple->sms_prot1->equals(SmsProtocol::SMPP()));

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

        $this->assertTrue(Planet::Jupiter()->equals($tuple->planet_rng->getLower()));
        $this->assertTrue(Planet::Neptune()->equals($tuple->planet_rng->getUpper()));
    }

    public function testSerializing()
    {
        $conn = $this->getIvoryConnection();
        $this->assertTrue(
            $conn->querySingleValue(
                'SELECT %planet < %planet',
                Planet::Venus(),
                'Uranus'
            )
        );

        try {
            $conn->querySingleValue('SELECT %planet', 'Pluto');
            $this->fail(\InvalidArgumentException::class . ' was expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $conn->querySingleValue('SELECT %planet', SmsProtocol::Jupiter());
            $this->fail(\InvalidArgumentException::class . ' was expected');
        } catch (\InvalidArgumentException $e) {
        }
    }
}
