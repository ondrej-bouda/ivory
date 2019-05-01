<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Connection\Connection;
use Ivory\Connection\ITxHandle;
use Ivory\Exception\IncomparableException;
use Ivory\IvoryTestCase;
use Ivory\Type\Postgresql\StrictEnumType;
use Ivory\Value\Range;

class StrictEnumTypeTest extends IvoryTestCase
{
    /** @var Connection */
    private $conn;
    /** @var ITxHandle */
    private $tx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->tx = $this->conn->startTransaction();

        $this->conn->command(
            "CREATE TYPE chocolate_bar AS ENUM ('Mars', 'Snickers', 'Twix')"
        );
        $this->conn->command(
            "CREATE TYPE planet AS ENUM ('Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune')"
        );
        $this->conn->command(
            "CREATE TYPE planet_range AS RANGE (SUBTYPE = planet)"
        );

        $typeRegister = $this->conn->getTypeRegister();
        $typeRegister->registerType(new StrictEnumType('public', 'chocolate_bar', ChocolateBar::class));
        $typeRegister->registerType(new StrictEnumType('public', 'planet', Planet::class));
    }

    protected function tearDown(): void
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

        assert($tuple->bar1 instanceof ChocolateBar);
        assert($tuple->bar2 instanceof ChocolateBar);
        assert($tuple->planet1 instanceof Planet);
        assert($tuple->planet2 instanceof Planet);
        assert($tuple->planet3 instanceof Planet);
        assert($tuple->planet_rng instanceof Range);

        self::assertTrue($tuple->bar2 == ChocolateBar::Twix());
        self::assertTrue($tuple->bar2->equals(ChocolateBar::Twix()));

        self::assertTrue($tuple->planet1 == $tuple->planet2);
        self::assertTrue($tuple->planet1 != $tuple->planet3);
        self::assertTrue($tuple->bar1 != $tuple->planet1);
        self::assertTrue($tuple->bar2 != $tuple->planet3);

        self::assertEquals(0, $tuple->planet1->compareTo($tuple->planet2));
        self::assertLessThan(0, $tuple->planet1->compareTo($tuple->planet3));
        try {
            $tuple->planet1->compareTo($tuple->bar1);
            self::fail(IncomparableException::class . ' was expected');
        } catch (IncomparableException $e) {
        }

        self::assertTrue(Planet::Jupiter()->equals($tuple->planet_rng->getLower()));
        self::assertTrue(Planet::Neptune()->equals($tuple->planet_rng->getUpper()));
    }

    public function testSerializing()
    {
        $conn = $this->getIvoryConnection();
        self::assertTrue(
            $conn->querySingleValue(
                'SELECT %planet < %planet',
                Planet::Venus(),
                new Planet('Uranus')
            )
        );

        try {
            $conn->querySingleValue('SELECT %planet', 'Mars');
            self::fail(\InvalidArgumentException::class . ' was expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $conn->querySingleValue('SELECT %planet', ChocolateBar::Mars());
            self::fail(\InvalidArgumentException::class . ' was expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testEquals()
    {
        self::assertTrue($this->isGiantPlanet(Planet::Uranus()));
        self::assertFalse($this->isGiantPlanet(Planet::Earth()));
    }

    public function testConstruction()
    {
        /** @noinspection PhpNonStrictObjectEqualityInspection intentional - we want to test the comparison */
        self::assertTrue(Planet::Mars() == new Planet('Mars'));

        try {
            new Planet('mars');
            self::fail(\InvalidArgumentException::class . ' expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    private function isGiantPlanet(Planet $p): bool
    {
        switch ($p) {
            case Planet::Mercury():
            case Planet::Venus():
            case Planet::Earth():
            case Planet::Mars():
                return false;
            case Planet::Jupiter():
            case Planet::Saturn():
            case Planet::Uranus():
            case Planet::Neptune():
                return true;
            default:
                throw new \UnexpectedValueException();
        }
    }
}
