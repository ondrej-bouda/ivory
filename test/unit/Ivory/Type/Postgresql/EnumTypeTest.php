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
            "CREATE TYPE chocolate_bar AS ENUM ('Mars', 'Snickers', 'Twix')"
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
                 'Mars'::chocolate_bar AS bar1,
                 'Twix'::chocolate_bar AS bar2,
                 'Mars'::planet AS planet1,
                 'Mars'::planet AS planet2,
                 'Jupiter'::planet AS planet3,
                 planet_range('Jupiter', 'Neptune') AS planet_rng"
        );

        assert($tuple->bar1 instanceof EnumItem);
        assert($tuple->bar2 instanceof EnumItem);
        assert($tuple->planet1 instanceof EnumItem);
        assert($tuple->planet2 instanceof EnumItem);
        assert($tuple->planet3 instanceof EnumItem);
        assert($tuple->planet_rng instanceof Range);

        $this->assertSame('Twix', $tuple->bar2->getValue());

        $this->assertTrue($tuple->planet1->equals($tuple->planet2));
        $this->assertFalse($tuple->planet1->equals($tuple->planet3));
        $this->assertFalse($tuple->bar1->equals($tuple->planet1));
        $this->assertFalse($tuple->bar2->equals($tuple->planet3));

        $this->assertEquals(0, $tuple->planet1->compareTo($tuple->planet2));
        $this->assertLessThan(0, $tuple->planet1->compareTo($tuple->planet3));
        try {
            $tuple->planet1->compareTo($tuple->bar1);
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

        try {
            $conn->querySingleValue('SELECT %planet', 'Pluto');
            $this->fail('A warning was expected');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
        }
    }
}
