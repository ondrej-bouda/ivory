<?php
namespace Ivory\Relation;

use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedColumnException;

class RenamedRelationTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
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
        $this->assertSame(['bar' => 'abc'], $renamed->tuple()->toMap());
    }

    public function testComplex()
    {
        $rel = $this->conn->query(
            "SELECT 1, 'abc', false AS b, true AS c, NULL::JSON AS d"
        );
        $renamed = $rel->rename([1 => 'str', 2 => 'bool', 'd' => 'json']);
        $this->assertSame(
            ['' => 1, 'str' => 'abc', 'bool' => false, 'c' => true, 'json' => null],
            iterator_to_array($renamed->tuple())
        );
    }

    public function testReuseSource()
    {
        $rel = $this->conn->query(
            "SELECT 'abc' AS foo"
        );
        $renamed = $rel->rename(['foo' => 'bar']);
        $this->assertSame(['bar' => 'abc'], $renamed->tuple()->toMap());
        $this->assertSame(['foo' => 'abc'], $rel->tuple()->toMap());
        $other = $rel->rename([0 => 'baz']);
        $this->assertSame(['baz' => 'abc'], $other->tuple()->toMap());

        $relSimult = $this->conn->query(
            'VALUES (1), (2)'
        );
        $renOne = $relSimult->rename(['a']);
        $renTwo = $relSimult->rename(['b']);
        /** @var $outer ITuple */
        foreach ($renOne as $i => $outer) {
            $this->assertSame(['a' => $i + 1], $outer->toMap(), "row $i");
            /** @var $inner ITuple */
            foreach ($renTwo as $j => $inner) {
                $this->assertSame(['b' => $j + 1], $inner->toMap(), "row $i|$j");
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
        $this->assertSame(['F' => 'abc', 'bar' => 4, 'BZ' => 5], $renamed->tuple()->toMap());
    }

    public function testSimpleMacros()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS ab, 3 AS b, 4 AS xyxz'
        );
        $this->assertSame(
            ['c ' => 1, 'c b' => 2, 'b' => 3, 'xyxz' => 4],
            $rel->rename(['a*' => 'c *'])->tuple()->toMap()
        );
        $this->assertSame(
            ['a' => 1, 'ab' => 2, 'c' => 3, 'xyxz' => 4],
            $rel->rename(['b*' => 'c'])->tuple()->toMap()
        );
        $this->assertSame(
            ['c a' => 1, 'c ab' => 2, 'c b' => 3, 'c xyxz' => 4],
            $rel->rename(['*' => 'c *'])->tuple()->toMap()
        );
        $this->assertSame(
            ['c \\ * ' => 1, 'c \\ * b' => 2, 'b' => 3, 'xyxz' => 4],
            $rel->rename(['a*' => 'c \\\\ \\* *'])->tuple()->toMap()
        );
        $this->assertSame(
            ['d' => 1, 'ac' => 2, 'c' => 3, 'xyxz' => 4],
            $rel->rename(['*b' => '*c', 'a*' => 'd'])->tuple()->toMap()
        );
        $this->assertSame(
            ['a' => 1, 'ab' => 2, 'b' => 3, 'XyYzZ' => 4],
            $rel->rename(['x*x**' => 'X*Y*Z*'])->tuple()->toMap()
        );
    }

    public function testPcreMacros()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS ab, 3 AS b'
        );
        $this->assertSame(['c' => 1, 'cb' => 2, 'b' => 3], $rel->rename(['/A(.*)/i' => 'c$1'])->tuple()->toMap());
        $this->assertSame(['a' => 1, 'ba' => 2, 'b' => 3], $rel->rename(['/(.)(.)/' => '$2$1'])->tuple()->toMap());
    }

    public function testMultipleMatchingColumns()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS a, 3 AS ab'
        );
        $this->assertSame(['c' => 1, 'cb' => 3], $rel->rename(['a*' => 'c*'])->tuple()->toMap());
        $tuple = $rel->rename(['a' => 'c'])->tuple();
        $expectedVals = [['c', 1], ['c', 2], ['ab', 3]];
        $i = 0;
        foreach ($tuple as $nm => $val) {
            $this->assertSame($expectedVals[$i][0], $nm, "col $nm");
            $this->assertSame($expectedVals[$i][1], $val, "col $nm");
            $i++;
        }
    }

    public function testCol()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (1, 2), (3, 4), (5, 6)) v (a, b)'
        );
        $renamed = $rel->rename(['a' => 'c']);

        $this->assertSame([1, 3, 5], $renamed->col('c')->toArray());
        $this->assertSame([2, 4, 6], $renamed->col('b')->toArray());
        try {
            $renamed->col('a');
            $this->fail(UndefinedColumnException::class . ' expected');
        }
        catch (UndefinedColumnException $e) {
        }

        $this->assertSame(
            [2, 12, 30],
            $renamed->col(function (ITuple $tuple) { return $tuple['b'] * $tuple['c']; })->toArray()
        );

        $this->assertSame('c', $renamed->col(0)->getName());
    }
}
