<?php
declare(strict_types=1);
namespace Ivory\Query;

use Ivory\Connection\IConnection;
use Ivory\Ivory;
use Ivory\IvoryTestCase;
use Ivory\Lang\Sql\ISqlExpression;
use Ivory\Lang\Sql\ISqlSortExpression;

class SortedRelationDefinitionTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var IRelationDefinition */
    private $relDef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->relDef = SqlRelationDefinition::fromSql(
            'SELECT * FROM (VALUES (1,2), (4,3), (6,6), (7,8)) v (a, b)'
        );
    }


    public function testStringExpr()
    {
        $sorted = $this->relDef->sort('a %% % = 1 DESC', 2);
        $rel = $this->conn->query($sorted);

        $this->assertSame(
            [
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
                ['a' => 4, 'b' => 3],
                ['a' => 6, 'b' => 6],
            ],
            $rel->toArray()
        );
    }

    public function testSqlPatternExpr()
    {
        $parser = Ivory::getSqlPatternParser();
        $pattern = $parser->parse('a %% % = 1 DESC');
        $sorted = $this->relDef->sort($pattern, 2);
        $rel = $this->conn->query($sorted);

        $this->assertSame(
            [
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
                ['a' => 4, 'b' => 3],
                ['a' => 6, 'b' => 6],
            ],
            $rel->toArray()
        );
    }

    public function testSqlSortExpressionExpr()
    {
        $sortExpr = new class() implements ISqlSortExpression {
            public function getExpression(): ISqlExpression
            {
                return new class() implements ISqlExpression {
                    public function getSql(): string
                    {
                        return 'a >= b';
                    }
                };
            }

            public function getDirection(): string
            {
                return ISqlSortExpression::DESC;
            }
        };
        $sorted = $this->relDef->sort($sortExpr);
        $rel = $this->conn->query($sorted);

        $this->assertSame(
            [
                ['a' => 4, 'b' => 3],
                ['a' => 6, 'b' => 6],
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
            ],
            $rel->toArray()
        );
    }

    public function testArrayExpr()
    {
        $sorted = $this->relDef->sort([
            'a > b',
            ['%ident < %', 'a', 7],
        ]);
        $rel = $this->conn->query($sorted);

        $this->assertSame(
            [
                ['a' => 7, 'b' => 8],
                ['a' => 1, 'b' => 2],
                ['a' => 6, 'b' => 6],
                ['a' => 4, 'b' => 3],
            ],
            $rel->toArray()
        );
    }

    public function testMultipleExpressions()
    {
        $sorted = $this->relDef
            ->sort('a > b')
            ->sort('a != b');
        $rel = $this->conn->query($sorted);

        $this->assertSame(
            [
                ['a' => 6, 'b' => 6],
                ['a' => 1, 'b' => 2],
                ['a' => 7, 'b' => 8],
                ['a' => 4, 'b' => 3],
            ],
            $rel->toArray()
        );
    }
}
