<?php
declare(strict_types=1);

namespace Ivory\Type;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Value\Decimal;
use Ivory\Value\Timestamp;
use Ivory\Value\TimestampTz;

/**
 * This test presents the type system support.
 */
class TypeRegisterTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
    {
        parent::setUp();
        $this->conn = $this->getIvoryConnection();
    }

    public function testStdTypeAbbreviations()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT %s::TEXT, %i::BIGINT, %num::NUMERIC, %f::float8, %ts::TIMESTAMP, %tstz::TIMESTAMPTZ',
            'foo',
            42,
            3.14,
            1.81,
            Timestamp::fromParts(2017, 9, 23, 17, 14, 0),
            TimestampTz::fromParts(2017, 9, 23, 17, 14, 1, new \DateTimeZone('Europe/Prague'))
        );

        $this->assertSame('foo', $tuple[0]);
        $this->assertSame(42, $tuple[1]);
        $this->assertEquals(Decimal::fromNumber(3.14), $tuple[2]);
        $this->assertSame(1.81, $tuple[3]);
        $this->assertEquals(Timestamp::fromParts(2017, 9, 23, 17, 14, 0), $tuple[4]);
        $this->assertEquals(
            TimestampTz::fromParts(2017, 9, 23, 17, 14, 1, new \DateTimeZone('Europe/Prague')),
            $tuple[5]
        );
    }

    public function testCustomTypeAbbreviations()
    {
        try {
            $this->conn->querySingleValue('SELECT %b', true);
            $this->fail(UndefinedTypeException::class . ' was expected');
        } catch (UndefinedTypeException $e) {
        }

        $this->conn->getTypeRegister()->registerTypeAbbreviation('b', 'pg_catalog', 'bool');
        $this->conn->flushTypeDictionary();

        $val = $this->conn->querySingleValue('SELECT %b', true);
        $this->assertSame(true, $val);
    }
}
