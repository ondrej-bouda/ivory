<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedColumnException;
use Ivory\IvoryTestCase;

class RenamedRelationTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testSimpleRename()
    {
        $rel = $this->conn->query(
            "SELECT 'abc' AS foo"
        );
        $renamed = $rel->rename(['foo' => 'bar']);
        self::assertSame(['bar' => 'abc'], $renamed->tuple()->toMap());
    }

    public function testComplex()
    {
        $rel = $this->conn->query(
            "SELECT 1, 'abc', false AS b, true AS c, NULL::JSON AS d"
        );
        $renamed = $rel->rename([1 => 'str', 2 => 'bool', 'd' => 'json']);
        self::assertSame(
            ['str' => 'abc', 'bool' => false, 'c' => true, 'json' => null],
            $renamed->tuple()->toMap()
        );
    }

    public function testReuseSource()
    {
        $rel = $this->conn->query(
            "SELECT 'abc' AS foo"
        );
        $renamed = $rel->rename(['foo' => 'bar']);
        self::assertSame(['bar' => 'abc'], $renamed->tuple()->toMap());
        self::assertSame(['foo' => 'abc'], $rel->tuple()->toMap());
        $other = $rel->rename([0 => 'baz']);
        self::assertSame(['baz' => 'abc'], $other->tuple()->toMap());

        $relSimult = $this->conn->query(
            'VALUES (1), (2)'
        );
        $renOne = $relSimult->rename(['a']);
        $renTwo = $relSimult->rename(['b']);
        foreach ($renOne as $i => $outer) {
            assert($outer instanceof ITuple);
            self::assertSame(['a' => $i + 1], $outer->toMap(), "row $i");
            foreach ($renTwo as $j => $inner) {
                assert($inner instanceof ITuple);
                self::assertSame(['b' => $j + 1], $inner->toMap(), "row $i|$j");
            }
        }
    }

    public function testTraversableSpec()
    {
        $rel = $this->conn->query(
            "SELECT 'abc' AS foo, 4 AS bar, 5 AS baz"
        );
        // testing by a generator to assure the traversable is traversed only once
        $fn = function () {
            yield 'baz' => 'BZ';
            yield 'F';
        };
        $gen = $fn();
        $renamed = $rel->rename($gen);
        self::assertSame(['F' => 'abc', 'bar' => 4, 'BZ' => 5], $renamed->tuple()->toMap());
    }

    public function testSimpleMacros()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS ab, 3 AS b, 4 AS xyxz'
        );
        self::assertSame(
            ['c ' => 1, 'c b' => 2, 'b' => 3, 'xyxz' => 4],
            $rel->rename(['a*' => 'c *'])->tuple()->toMap()
        );
        self::assertSame(
            ['a' => 1, 'ab' => 2, 'c' => 3, 'xyxz' => 4],
            $rel->rename(['b*' => 'c'])->tuple()->toMap()
        );
        self::assertSame(
            ['c a' => 1, 'c ab' => 2, 'c b' => 3, 'c xyxz' => 4],
            $rel->rename(['*' => 'c *'])->tuple()->toMap()
        );
        self::assertSame(
            ['c \\ * ' => 1, 'c \\ * b' => 2, 'b' => 3, 'xyxz' => 4],
            $rel->rename(['a*' => 'c \\\\ \\* *'])->tuple()->toMap()
        );
        self::assertSame(
            ['d' => 1, 'ac' => 2, 'c' => 3, 'xyxz' => 4],
            $rel->rename(['*b' => '*c', 'a*' => 'd'])->tuple()->toMap()
        );
        self::assertSame(
            ['a' => 1, 'ab' => 2, 'b' => 3, 'XyYzZ' => 4],
            $rel->rename(['x*x**' => 'X*Y*Z*'])->tuple()->toMap()
        );
    }

    public function testPcreMacros()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS ab, 3 AS b'
        );
        self::assertSame(['c' => 1, 'cb' => 2, 'b' => 3], $rel->rename(['/A(.*)/i' => 'c$1'])->tuple()->toMap());
        self::assertSame(['a' => 1, 'ba' => 2, 'b' => 3], $rel->rename(['/(.)(.)/' => '$2$1'])->tuple()->toMap());
    }

    public function testMultipleMatchingColumns()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS a, 3 AS ab'
        );
        $renamed = $rel->rename(['a' => 'c']);
        $expectedColNames = ['c', 'c', 'ab'];
        foreach ($renamed->getColumns() as $i => $column) {
            self::assertSame($expectedColNames[$i], $column->getName(), "iteration $i");
        }

        $renTuple = $renamed->tuple();
        self::assertSame([1, 2, 3], $renTuple->toList());
        self::assertSame(3, $renTuple->ab);
        self::assertTrue(isset($renTuple->c));
        self::assertFalse(isset($renTuple->a));
    }

    public function testMultipleMatchingColumnsWildcard()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS a, 3 AS ab'
        );
        $renamed = $rel->rename(['a*' => 'c*']);
        $expectedColNames = ['c', 'c', 'cb'];
        foreach ($renamed->getColumns() as $i => $column) {
            self::assertSame($expectedColNames[$i], $column->getName(), "iteration $i");
        }

        $renTuple = $renamed->tuple();
        self::assertSame([1, 2, 3], $renTuple->toList());
        self::assertSame(3, $renTuple->cb);
    }

    public function testCol()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (1, 2), (3, 4), (5, 6)) v (a, b)'
        );
        $renamed = $rel->rename(['a' => 'c']);

        self::assertSame([1, 3, 5], $renamed->col('c')->toArray());
        self::assertSame([2, 4, 6], $renamed->col('b')->toArray());
        try {
            $renamed->col('a');
            self::fail(UndefinedColumnException::class . ' expected');
        } catch (UndefinedColumnException $e) {
        }

        self::assertSame(
            [2, 12, 30],
            $renamed->col(function (ITuple $tuple) { return $tuple->b * $tuple->c; })->toArray()
        );

        self::assertSame('c', $renamed->col(0)->getName());
    }
}
