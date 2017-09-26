<?php
declare(strict_types=1);

namespace Ivory\Showcase;

use Ivory\Connection\IConnection;
use Ivory\Connection\ITxHandle;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Type\ITypeDictionary;
use Ivory\Value\Date;
use Ivory\Value\Decimal;
use Ivory\Value\Json;

/**
 * This test presents the measures for querying the database.
 */
class QueryingTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var ITxHandle */
    private $transaction;
    /** @var ITypeDictionary */
    private $typeDict;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->transaction = $this->conn->startTransaction();
        $this->typeDict = $this->conn->getTypeDictionary();
    }

    protected function tearDown()
    {
        $this->transaction->rollback();

        parent::tearDown();
    }


    public function testSqlPatterns()
    {
        // Ivory introduces SQL patterns. They are similar to sprintf() but, when serializing to SQL, the actual types
        // defined on the database connection are used. See \Ivory\Lang\SqlPattern\SqlPattern class docs for details.
        $relDef = SqlRelationDefinition::fromPattern('SELECT %s, %num, %bool', "Ivory's escaping", '3.14', false);
        $sql = $relDef->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14, FALSE", $sql);

        // Moreover, the types need not be specified explicitly. There are rules for inferring the type from the actual
        // value.
        $relDef = SqlRelationDefinition::fromPattern('SELECT %, %, %', "Automatic type recognition", 3.14, false);
        $sql = $relDef->toSql($this->typeDict);
        $this->assertSame("SELECT 'Automatic type recognition', 3.14, FALSE", $sql);

        // All the standard PostgreSQL types are already set up in Ivory by default. Besides, there are special value
        // serializers for specific usage, e.g., LIKE operands.
        $this->assertTrue($this->conn->querySingleValue("SELECT 'foobar' LIKE %_like_", 'oo'));

        // As usual, both the types of placeholders and the rules for recognizing types from values are configurable.
        $this->conn->getTypeRegister()->registerTypeAbbreviation('js', 'pg_catalog', 'json');
        $this->conn->getTypeRegister()->addTypeRecognitionRule(\stdClass::class, 'pg_catalog', 'json');
        $this->conn->flushTypeDictionary(); // the type dictionary was changed while in use - explicit flush necessary

        $relDef = SqlRelationDefinition::fromPattern('SELECT %js, %', Json::null(), (object)['a' => 42]);
        $sql = $relDef->toSql($this->conn->getTypeDictionary());
        $this->assertSame("SELECT pg_catalog.json 'null', pg_catalog.json '{\"a\":42}'", $sql);

        // Also, brand new types may be introduced. See \Ivory\Showcase\TypeSystemTest::testCustomType().
    }

    public function testRelationDefinition()
    {
        // Ivory speaks in terms of relations and their definitions. Querying the database by supplying a relation
        // definition, one gets a relation, i.e., the tabular data. Usually, relations are defined using SQL patterns.
        $relDef = SqlRelationDefinition::fromPattern('SELECT %bool, %, %num', true, 'str', 3.14);

        // The definition may directly be used for querying the database...
        $tuple = $this->conn->querySingleTuple($relDef);
        $this->assertEquals([true, 'str', Decimal::fromNumber('3.14')], $tuple->toList());

        // ...or as a base for another definition. Note the construction from "fragments" - parts of the SQL pattern put together.
        $valsDef = SqlRelationDefinition::fromFragments(
            'VALUES (%, %),', 4, Date::fromParts(2017, 2, 25),
            '       (%, %)', 7, null
        );
        $relDef = SqlRelationDefinition::fromPattern(
            'SELECT * FROM (%rel) AS t (id, creat)',
            $valsDef
        );
        $this->assertCount(2, $this->conn->query($relDef));

        // Since the definition is not tied to a connection, it may be cached and retrieved later.
        $serialized = serialize($relDef);
        $this->assertCount(2, $this->conn->query(unserialize($serialized)));
    }
}
