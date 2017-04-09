<?php
namespace Ivory\Showcase;

use Ivory\Connection\IConnection;
use Ivory\Connection\ITypeControl;
use Ivory\Type\INamedType;
use Ivory\Value\Composite;
use Ivory\Value\Json;
use Ivory\Value\Polygon;
use Ivory\Value\Range;
use Ivory\Value\XmlContent;
use Ivory\Value\XmlDocument;

/**
 * This test presents the type system support.
 */
class TypeSystemTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
    {
        parent::setUp();
        $this->conn = $this->getIvoryConnection();
    }

    /**
     * Ivory has no problems handling complex types.
     */
    public function testComplexTypes()
    {
        $tuple = $this->conn->querySingleTuple(
            <<<SQL
            SELECT ARRAY[
                    daterange('2015-05-19', '2015-12-01'),
                    daterange('2015-12-01', '2017-02-19'),
                    daterange('2017-02-19', NULL)
                  ],
                  '{"k1": 42, "k2": false}'::json,
                  XMLPARSE(DOCUMENT '<?xml version="1.0"?><book><title>Manual</title></book>') AS xml,
                  polygon '((1,1), (1, 5), (4, 3))',
                  ROW(1, 2, 'foo')
SQL
        );

        /** @var Range[] $rangeArray */
        $rangeArray = $tuple['array'];
        $this->assertInstanceOf(Range::class, $rangeArray[2]);
        $this->assertSame('19.2.2017', $rangeArray[2]->getUpper()->format('j.n.Y'));
        $this->assertFalse($rangeArray[3]->isFinite());

        /** @var Json $json */
        $json = $tuple['json'];
        $this->assertInstanceOf(Json::class, $json);
        $this->assertEquals((object)['k1' => 42, 'k2' => false], $json->getValue());

        /** @var XmlDocument $xml */
        $xml = $tuple['xml'];
        $this->assertInstanceOf(XmlContent::class, $xml);
        $this->assertEquals(['Manual'], $xml->toSimpleXMLElement()->xpath('//title/text()'));

        /** @var Polygon $polygon */
        $polygon = $tuple['polygon'];
        $this->assertInstanceOf(Polygon::class, $polygon);
        $this->assertEquals(6, $polygon->getArea(), '', 1e-12);

        /** @var Composite $row */
        $row = $tuple['row'];
        $this->assertInstanceOf(Composite::class, $row);
        $this->assertSame(['1', '2', 'foo'], $row->toList());
    }

    /**
     * Whenever Ivory does not recognize a type in the result, it introspects the database and fetches and processes the
     * type automatically.
     */
    public function testDynamic()
    {
        $this->conn->startTransaction();
        try {
            // Create a brand new type.
            $this->conn->command(
                'CREATE TYPE __ivory_test_composite_type AS (
                   num INT,
                   chr TEXT
                 )'
            );

            // Use it in a query.
            $composite = $this->conn->querySingleValue("SELECT (1, 'a')::__ivory_test_composite_type");
            $this->assertSame('a', $composite->chr);
        } finally {
            $this->conn->rollback();
        }
    }

    /**
     * Type converters for custom user-defined types may easily be plugged into Ivory. Also, any type converter shipped
     * with Ivory may be overridden with a custom one.
     */
    public function testCustomTypeConverter()
    {
        // Just implement the INamedType (and ITotallyOrderedType, for the type to be usable in ranges)...
        $customTimeConverter = new class implements INamedType {
            public function getSchemaName(): string
            {
                return 'pg_catalog';
            }

            public function getName(): string
            {
                return 'time';
            }

            public function parseValue($str)
            {
                if ($str === null) {
                    return null;
                }

                $dt = \DateTime::createFromFormat('H:i:s.u', $str);
                if ($dt === false) {
                    $dt = \DateTime::createFromFormat('H:i:s', $str);
                }

                if ($dt !== false) {
                    return $dt;
                } else {
                    throw new \InvalidArgumentException('Invalid format to parse a time');
                }
            }

            public function serializeValue($val): string
            {
                if ($val === null) {
                    return 'NULL';
                } elseif ($val instanceof \DateTime) {
                    return $val->format("'H:i:s.u'");
                } else {
                    throw new \InvalidArgumentException('Invalid value to serialize as TIME');
                }
            }
        };

        // ...and register it. Either at the global level, or just for a specific connection.
        $this->conn->getTypeRegister()->registerType('pg_catalog', 'time', $customTimeConverter);
        // NOTE: necessary as the cached dictionary refers to type converters defined in time of caching the dictionary
        $this->conn->flushCache(ITypeControl::TYPE_DICTIONARY_CACHE_KEY);

        $val = $this->conn->querySingleValue("SELECT TIME '16:42'");
        $this->assertEquals(new \DateTime('16:42:00'), $val);

        $eq = $this->conn->querySingleValue("SELECT TIME '13:30' = %time", new \DateTime('13:30:00'));
        $this->assertTrue($eq);
    }
}
