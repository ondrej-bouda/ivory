<?php
declare(strict_types=1);
namespace Ivory\Showcase;

use Ivory\Connection\IConnection;
use Ivory\Connection\ITxHandle;
use Ivory\IvoryTestCase;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Relation\ICursor;
use Ivory\Relation\ITuple;
use Ivory\Type\ITypeDictionary;
use Ivory\Value\Date;
use Ivory\Value\Decimal;
use Ivory\Value\Json;

/**
 * This test presents the measures for querying the database.
 */
class QueryingTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var ITxHandle */
    private $transaction;
    /** @var ITypeDictionary */
    private $typeDict;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->transaction = $this->conn->startTransaction();
        $this->typeDict = $this->conn->getTypeDictionary();
    }

    protected function tearDown(): void
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
        self::assertSame("SELECT pg_catalog.text 'Ivory''s escaping', 3.14::pg_catalog.numeric, FALSE", $sql);

        // Moreover, the types need not be specified explicitly. There are rules for inferring the type from the actual
        // value.
        $relDef = SqlRelationDefinition::fromPattern('SELECT %, %, %', "Automatic type inference", 3.14, false);
        $sql = $relDef->toSql($this->typeDict);
        self::assertSame("SELECT pg_catalog.text 'Automatic type inference', 3.14::pg_catalog.float8, FALSE", $sql);

        // All the standard PostgreSQL types are already set up in Ivory by default. Besides, there are special value
        // serializers for specific usage, e.g., LIKE operands.
        self::assertTrue($this->conn->querySingleValue("SELECT 'foobar' LIKE %_like_", 'oo')); // yields LIKE '%oo%'

        // As usual, both the types of placeholders and the rules for infering types from values are configurable.
        $this->conn->getTypeRegister()->registerTypeAbbreviation('js', 'pg_catalog', 'json');
        $this->conn->getTypeRegister()->registerTypeInferenceRule(\stdClass::class, 'pg_catalog', 'json');
        $this->conn->flushTypeDictionary(); // the type dictionary was changed while in use - explicit flush necessary

        $relDef = SqlRelationDefinition::fromPattern('SELECT %js, %', Json::null(), (object)['a' => 42]);
        $sql = $relDef->toSql($this->conn->getTypeDictionary());
        self::assertSame("SELECT pg_catalog.json 'null', pg_catalog.json '{\"a\":42}'", $sql);

        // Also, brand new types may be introduced. See \Ivory\Showcase\TypeSystemTest::testCustomType().
    }

    public function testRelationDefinition()
    {
        // Ivory speaks in terms of relations and their definitions. Querying the database by supplying a relation
        // definition, one gets a relation, i.e., the tabular data. Usually, relations are defined using SQL patterns.
        $relDef = SqlRelationDefinition::fromPattern('SELECT %bool, %, %num', true, 'str', 3.14);

        // The definition may directly be used for querying the database...
        $tuple = $this->conn->querySingleTuple($relDef);
        self::assertEquals([true, 'str', Decimal::fromNumber('3.14')], $tuple->toList());

        // ...or as a base for another definition. Note the construction from "fragments" - parts of the SQL pattern put
        // together. Also note we specify data type explicitly for the date column so that a null may be matched with
        // date.
        $valsDef = SqlRelationDefinition::fromFragments(
            'VALUES (%, %date),', 4, Date::fromParts(2017, 2, 25),
            '       (%, %date)', 7, null
        );
        $relDef = SqlRelationDefinition::fromPattern(
            'SELECT * FROM (%rel) AS t (id, creat)',
            $valsDef
        );
        self::assertCount(2, $this->conn->query($relDef));

        // Since the definition is not tied to a connection, it may be cached and retrieved later.
        $serialized = serialize($relDef);
        self::assertCount(2, $this->conn->query(unserialize($serialized)));
    }

    public function testAsynchronousQueries()
    {
        // It is very easy to execute a query in the background and collect results later.
        $startTime = microtime(true);
        $asyncResult = $this->conn->queryAsync('SELECT pg_sleep(2) /* an expensive query */, 3.14');
        $sentTime = microtime(true);
        sleep(2); // some useful computation on the PHP side; avoid querying the database within the same connection!
        $nextTime = microtime(true);
        $result = $asyncResult->getResult();
        $returnTime = microtime(true);

        self::assertEquals(Decimal::fromNumber(3.14), $result->value(1));
        self::assertLessThan(1, $sentTime - $startTime, 'queryAsync() will return without waiting');
        self::assertLessThan(1, $returnTime - $nextTime, 'result will be ready when asked for');

        // Commands may also be run asynchronously.
        $startTime = microtime(true);
        $asyncResult = $this->conn->commandAsync("DO LANGUAGE plpgsql 'BEGIN PERFORM pg_sleep(2); END'");
        $sentTime = microtime(true);
        $result = $asyncResult->getResult();
        $returnTime = microtime(true);

        self::assertSame('DO', $result->getCommandTag());
        self::assertLessThan(1, $sentTime - $startTime, 'commandAsync() will return without waiting');
        $delta = .001;
        self::assertGreaterThan(2 - $delta, $returnTime - $sentTime, 'next() will wait until the command is finished');
    }

    public function testCursors()
    {
        // Operations on cursors are encapsulated in the ICursor interface.

        // Ivory can both declare new cursors...
        $relDef = SqlRelationDefinition::fromSql("VALUES ('a'), ('b'), ('c'), ('d'), ('e')");
        // (note we are in a transaction due to setUp())
        $curOne = $this->conn->declareCursor('cur1', $relDef, ICursor::SCROLLABLE);
        // ...and take an already declared cursor from a refcursor value.
        $this->conn->command(
            <<<'SQL'
            CREATE FUNCTION get_cur() RETURNS refcursor AS $$
            DECLARE
                cur refcursor;
            BEGIN
                OPEN cur FOR VALUES ('x'), ('y'), ('z');
                RETURN cur;
            END;
            $$ LANGUAGE plpgsql
SQL
        );
        $curTwo = $this->conn->querySingleValue('SELECT get_cur()');

        // Any low-level operations are supported.
        // (Depending on whether the cursor is scrollable, one can do any movement/fetching, or just serial operations.)
        self::assertSame('c', $curOne->fetchAt(3)->value(0));
        self::assertSame('d', $curOne->fetch()->value(0));
        self::assertSame('b', $curOne->fetch(-2)->value(0));
        self::assertSame(1, $curOne->moveBy(2));
        self::assertSame('e', $curOne->fetch()->value(0));

        // Multiple rows may be fetched at once, forming a relation.
        $curOne->moveTo(0);
        self::assertSame(['a', 'b', 'c'], $curOne->fetchMulti(3)->col(0)->toArray());

        // ICursor is a traversable object, so it is trivial to process all rows.
        $expectedTupleValues = [1 => 'x', 2 => 'y', 3 => 'z'];
        foreach ($curTwo as $i => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($expectedTupleValues[$i], $tuple->value(0));
        }

        // Fetching the rows one-by-one is, however, slow - for each row, a complete PHP -> DB -> PHP loop is done.
        // ICursor extends the getIterator() method with an optional $bufferSize parameter, which leads to using a
        // buffer: a batch of $bufferSize rows is fetched at once and, while iterating through the rows of the batch,
        // rows from the following batch are fetch *in the background* so they are ready once the current buffer is
        // processed.
        $bigRelDef = SqlRelationDefinition::fromSql('SELECT generate_series(1, 100000)');
        $curThree = $this->conn->declareCursor('cur3', $bigRelDef);
        $fetchedValues = [];
        foreach ($curThree->getIterator(1000) as $tuple) { // fetch 1000 rows at once
            assert($tuple instanceof ITuple);                  // meanwhile, the next 1000 are fetched in the background
            $fetchedValues[] = $tuple->value(0);
        }
        self::assertSame(range(1, 100000), $fetchedValues);
    }
}
