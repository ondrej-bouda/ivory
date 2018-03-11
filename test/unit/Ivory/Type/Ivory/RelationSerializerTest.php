<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;
use Ivory\IvoryTestCase;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Result\IQueryResult;
use Ivory\Type\Std\BigIntSafeType;
use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;

class RelationSerializerTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var RelationSerializer */
    private $relationSerializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->relationSerializer = new RelationSerializer($this->conn);
    }

    public function testSerializeRelationDefinition()
    {
        $relDef = SqlRelationDefinition::fromSql(
            "VALUES (1, 'a'), (3, 'b'), (2, 'c')"
        );
        $this->assertSame(
            "VALUES (1, 'a'), (3, 'b'), (2, 'c')",
            $this->relationSerializer->serializeValue($relDef)
        );
    }

    /**
     * A helper method making a relation from the serialized form of the relation given by an SQL query.
     *
     * Using this helper, the test cases do not check for a specific serialization of a given relation. Instead, the
     * tests check whether a relation build on the serialized values has the expected form.
     *
     * @param string $sqlQuery
     * @return IQueryResult
     */
    private function queryUsingSerializedRelation(string $sqlQuery): IQueryResult
    {
        $inputRel = $this->conn->query($sqlQuery);
        $serialized = $this->relationSerializer->serializeValue($inputRel);
        $checkRel = $this->conn->query("SELECT * FROM ($serialized) rel");
        return $checkRel;
    }

    public function testSerializeRelation()
    {
        $checkRel = $this->queryUsingSerializedRelation(<<<SQL
SELECT 1::BIGINT AS "Num", 'a'::TEXT AS char
UNION
SELECT 3, 'b'
UNION
SELECT 2, 'c'
ORDER BY char
SQL
        );
        $this->assertSame(3, $checkRel->count());
        $this->assertSame(2, count($checkRel->getColumns()));
        if (PHP_INT_SIZE >= 8) {
            $this->assertInstanceOf(IntegerType::class, $checkRel->col('Num')->getType());
        } else {
            $this->assertInstanceOf(BigIntSafeType::class, $checkRel->col('Num')->getType());
        }
        $this->assertInstanceOf(StringType::class, $checkRel->col('char')->getType());
        $this->assertSame(
            [
                ['Num' => 1, 'char' => 'a'],
                ['Num' => 3, 'char' => 'b'],
                ['Num' => 2, 'char' => 'c'],
            ],
            $checkRel->toArray()
        );
    }

    public function testSerializeRelationWithNoRows()
    {
        $checkRel = $this->queryUsingSerializedRelation("SELECT 1 AS num, 'a' AS char WHERE FALSE");
        $this->assertSame(0, $checkRel->count());
        $this->assertSame(2, count($checkRel->getColumns()));
        $this->assertInstanceOf(IntegerType::class, $checkRel->col('num')->getType());
        $this->assertInstanceOf(StringType::class, $checkRel->col('char')->getType());
    }

    public function testSerializeRelationWithNoColumns()
    {
        $checkRel = $this->queryUsingSerializedRelation('SELECT');
        $this->assertSame(1, $checkRel->count());
        $this->assertEmpty($checkRel->getColumns());

        $checkRel = $this->queryUsingSerializedRelation('SELECT WHERE FALSE');
        $this->assertSame(0, $checkRel->count());
        $this->assertEmpty($checkRel->getColumns());

        $checkRel = $this->queryUsingSerializedRelation('SELECT UNION ALL SELECT UNION ALL SELECT');
        $this->assertSame(3, $checkRel->count());
        $this->assertEmpty($checkRel->getColumns());
    }

    public function testSerializeNull()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->relationSerializer->serializeValue(null);
    }
}
