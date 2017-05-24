<?php
namespace Ivory\Query;

use Ivory\Connection\IConnection;
use Ivory\Ivory;
use Ivory\Lang\Sql\ISqlPredicate;

class ConstrainedRelationRecipeTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var IRelationRecipe */
    private $recipe;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->recipe = SqlRelationRecipe::fromSql(
            'SELECT * FROM (VALUES (1,2), (4,3), (6,6), (7,8)) v (a, b)'
        );
    }


    public function testStringCondition()
    {
        $constrained = $this->recipe->where('a %% % = 1', 2);
        $rel = $this->conn->query($constrained);

        $this->assertSame(
            [
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
            ],
            $rel->toArray()
        );
    }

    public function testSqlPatternCondition()
    {
        $parser = Ivory::getSqlPatternParser();
        $pattern = $parser->parse('a %% % = 1');
        $constrained = $this->recipe->where($pattern, 2);
        $rel = $this->conn->query($constrained);

        $this->assertSame(
            [
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
            ],
            $rel->toArray()
        );
    }

    public function testSqlPredicateCondition()
    {
        $predicate = new class() implements ISqlPredicate {
            public function getSql(): string
            {
                return 'a >= b';
            }
        };
        $constrained = $this->recipe->where($predicate);
        $rel = $this->conn->query($constrained);

        $this->assertSame(
            [
                ['a' => 4, 'b' => 3],
                ['a' => 6, 'b' => 6],
            ],
            $rel->toArray()
        );
    }

    public function testMultipleConditions()
    {
        $constained = $this->recipe
            ->where('a > %', 1)
            ->where('a < 7')
            ->where('a != b');
        $rel = $this->conn->query($constained);

        $this->assertSame(
            [
                ['a' => 4, 'b' => 3],
            ],
            $rel->toArray()
        );
    }
}