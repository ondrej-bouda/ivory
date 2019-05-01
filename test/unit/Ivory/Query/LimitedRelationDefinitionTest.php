<?php
declare(strict_types=1);
namespace Ivory\Query;

use Ivory\Connection\IConnection;
use Ivory\IvoryTestCase;
use Ivory\Relation\ITuple;

class LimitedRelationDefinitionTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testLimitFirstOne()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'VALUES (1,2), (3,4), (3,6), (7,8)'
        );
        $limited = $relDef->limit(1);
        $rel = $this->conn->query($limited);

        self::assertSame(1, $rel->count());

        self::assertSame([1, 2], $rel->tuple(0)->toList());
        try {
            $rel->tuple(1);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        foreach ($rel as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame([1, 2], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(1, $i);
    }

    public function testLimitFirstThree()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $relDef->limit(3);
        $rel = $this->conn->query($limited);

        self::assertSame(3, $rel->count());

        self::assertSame([1, 2], $rel->tuple(0)->toList());
        self::assertSame([3, 4], $rel->tuple(1)->toList());
        self::assertSame([5, 6], $rel->tuple(2)->toList());
        try {
            $rel->tuple(3);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[1, 2], [3, 4], [5, 6]];
        foreach ($rel as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(3, $i);
    }

    public function testLimitTwoOffsetOne()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $relDef->limit(2, 1);
        $rel = $this->conn->query($limited);

        self::assertSame(2, $rel->count());

        self::assertSame([3, 4], $rel->tuple(0)->toList());
        self::assertSame([5, 6], $rel->tuple(1)->toList());
        try {
            $rel->tuple(2);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6]];
        foreach ($rel as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(2, $i);
    }

    public function testLimitTwoOffsetOneNotEnough()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'VALUES (1,2), (3,4)'
        );
        $limited = $relDef->limit(2, 1);
        $rel = $this->conn->query($limited);

        self::assertSame(1, $rel->count());

        self::assertSame([3, 4], $rel->tuple(0)->toList());
        try {
            $rel->tuple(1);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4]];
        foreach ($rel as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(1, $i);
    }

    public function testOffsetOneUnlimited()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $relDef->limit(null, 1);
        $rel = $this->conn->query($limited);

        self::assertSame(3, $rel->count());

        self::assertSame([3, 4], $rel->tuple(0)->toList());
        self::assertSame([5, 6], $rel->tuple(1)->toList());
        self::assertSame([7, 8], $rel->tuple(2)->toList());
        try {
            $rel->tuple(3);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6], [7, 8]];
        foreach ($rel as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(3, $i);
    }

    public function testLimitLimited()
    {
        $relDef = SqlRelationDefinition::fromSql(
            'SELECT * FROM (VALUES (1,2), (3,4), (5,6), (7,8)) v (a,b)'
        );
        $limited = $relDef->limit(2, 1);
        $limited2 = $limited->limit(1);
        $otherLimited = $relDef->limit(null, 3);
        $otherLimited2 = $otherLimited->limit(2);

        $rel = $this->conn->query($limited);
        $rel2 = $this->conn->query($limited2);
        $otherRel = $this->conn->query($otherLimited);
        $otherRel2 = $this->conn->query($otherLimited2);

        self::assertSame(
            [
                ['a' => 3, 'b' => 4],
                ['a' => 5, 'b' => 6],
            ],
            $rel->toArray()
        );
        self::assertSame(
            [
                ['a' => 3, 'b' => 4],
            ],
            $rel2->toArray()
        );
        self::assertSame(
            [
                ['a' => 7, 'b' => 8],
            ],
            $otherRel->toArray()
        );
        self::assertSame(
            [
                ['a' => 7, 'b' => 8],
            ],
            $otherRel2->toArray()
        );
    }
}
