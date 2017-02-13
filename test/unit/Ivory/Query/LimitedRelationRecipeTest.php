<?php
namespace Ivory\Query;

use Ivory\Connection\IConnection;
use Ivory\Relation\ITuple;

class LimitedRelationRecipeTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testLimitFirstOne()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'VALUES (1,2), (3,4), (3,6), (7,8)'
        );
        $limited = $recipe->limit(1);
        $rel = $this->conn->query($limited);

        $this->assertSame(1, $rel->count());

        $this->assertSame([1, 2], $rel->tuple(0)->toList());
        try {
            $rel->tuple(1);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        foreach ($rel as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame([1, 2], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(1, $i);
    }

    public function testLimitFirstThree()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $recipe->limit(3);
        $rel = $this->conn->query($limited);

        $this->assertSame(3, $rel->count());

        $this->assertSame([1, 2], $rel->tuple(0)->toList());
        $this->assertSame([3, 4], $rel->tuple(1)->toList());
        $this->assertSame([5, 6], $rel->tuple(2)->toList());
        try {
            $rel->tuple(3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[1, 2], [3, 4], [5, 6]];
        foreach ($rel as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(3, $i);
    }

    public function testLimitTwoOffsetOne()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $recipe->limit(2, 1);
        $rel = $this->conn->query($limited);

        $this->assertSame(2, $rel->count());

        $this->assertSame([3, 4], $rel->tuple(0)->toList());
        $this->assertSame([5, 6], $rel->tuple(1)->toList());
        try {
            $rel->tuple(2);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6]];
        foreach ($rel as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(2, $i);
    }

    public function testLimitTwoOffsetOneNotEnough()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'VALUES (1,2), (3,4)'
        );
        $limited = $recipe->limit(2, 1);
        $rel = $this->conn->query($limited);

        $this->assertSame(1, $rel->count());

        $this->assertSame([3, 4], $rel->tuple(0)->toList());
        try {
            $rel->tuple(1);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4]];
        foreach ($rel as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(1, $i);
    }

    public function testOffsetOneUnlimited()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $recipe->limit(null, 1);
        $rel = $this->conn->query($limited);

        $this->assertSame(3, $rel->count());

        $this->assertSame([3, 4], $rel->tuple(0)->toList());
        $this->assertSame([5, 6], $rel->tuple(1)->toList());
        $this->assertSame([7, 8], $rel->tuple(2)->toList());
        try {
            $rel->tuple(3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6], [7, 8]];
        foreach ($rel as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(3, $i);
    }

    public function testLimitLimited()
    {
        $recipe = SqlRelationRecipe::fromSql(
            'SELECT * FROM (VALUES (1,2), (3,4), (5,6), (7,8)) v (a,b)'
        );
        $limited = $recipe->limit(2, 1);
        $limited2 = $limited->limit(1);
        $otherLimited = $recipe->limit(null, 3);
        $otherLimited2 = $otherLimited->limit(2);

        $rel = $this->conn->query($limited);
        $rel2 = $this->conn->query($limited2);
        $otherRel = $this->conn->query($otherLimited);
        $otherRel2 = $this->conn->query($otherLimited2);

        $this->assertSame(
            [
                ['a' => 3, 'b' => 4],
                ['a' => 5, 'b' => 6],
            ],
            $rel->toArray()
        );
        $this->assertSame(
            [
                ['a' => 3, 'b' => 4],
            ],
            $rel2->toArray()
        );
        $this->assertSame(
            [
                ['a' => 7, 'b' => 8],
            ],
            $otherRel->toArray()
        );
        $this->assertSame(
            [
                ['a' => 7, 'b' => 8],
            ],
            $otherRel2->toArray()
        );
    }
}
