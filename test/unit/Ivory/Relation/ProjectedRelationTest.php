<?php
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;

class ProjectedRelationTest extends \Ivory\IvoryTestCase
{
    public function testSingleColSpec()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT 'abc' AS foo, 1, 42 AS the_answer,
                    7 AS a, 8 AS b, 'John' AS person_firstname, 'Doe' AS person_lastname,
                    100 AS a"
        );
        $this->assertEquals([['a' => 7, 'b' => 8]], $qr->project(['a', 'b'])->toArray());
        $this->assertEquals([7, 8], $qr->project(['a', 'b'])->tuple()->toList());
        $this->assertEquals([['a' => 8, 'b' => 7]], $qr->project(['a' => 'b', 'b' => 'a'])->toArray());
        $this->assertEquals([['the_answer' => 42, 'x' => 'abc']], $qr->project([1, 2, 'x' => 0])->toArray());
        $this->assertEquals([['sum' => 15]], $qr->project(['sum' => function (ITuple $t) { return $t['a'] + $t['b']; }])->toArray());

        try {
            $qr->project(['c']);
            $this->fail();
        }
        catch (UndefinedColumnException $e) {
        }

        try {
            $qr->project([-1]);
            $this->fail();
        }
        catch (UndefinedColumnException $e) {
        }

        try {
            $qr->project([8]);
            $this->fail();
        }
        catch (UndefinedColumnException $e) {
        }
    }

    public function testMultiColSpec()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT 'abc' AS foo, 1, 42 AS the_answer,
                    7 AS a, 8 AS b, 'John' AS person_firstname, 'Doe' AS person_lastname,
                    100 AS a"
        );
        $this->assertEquals([['p_firstname' => 'John', 'p_lastname' => 'Doe']], $qr->project(['p_*' => 'person_*'])->toArray());
        $this->assertEquals(
            [['foo' => 'abc', 'the_answer' => 42, 'a' => 7, 'b' => 8, 'person_firstname' => 'John', 'person_lastname' => 'Doe', 'copy' => 7]],
            $qr->project(['*', 'copy' => 'a'])->toArray()
        );
        $this->assertEquals(
            [['a' => 7, 'the_answer' => 42, 'person_firstname' => 'John', 'person_first' => 'John', 'person_last' => 'Doe']],
            $qr->project(['a', '/_.*R/i', '\1' => '/(.*)name$/'])->toArray()
        );

        try {
            $qr->project(['q*']);
        }
        catch (UndefinedColumnException $e) {
        }

        try {
            $qr->project(['/.{255}/']);
        }
        catch (UndefinedColumnException $e) {
        }
    }

    public function testColumnValues()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT *
             FROM (VALUES ('a', 1), ('b', 3), ('h', 3), ('w', -1)) v (x, n)"
        );

        $this->assertEquals([1, 3, 3, -1], $qr->col('n')->toArray());
        $this->assertEquals([1, 3, 3, -1], $qr->col(1)->toArray());
        $this->assertEquals(['a', 'b', 'h', 'w'], $qr->col('x')->toArray());

        $this->assertEquals([1, 3, 3, -1], $qr->project(['n'])->col('n')->toArray());
        $this->assertEquals([1, 3, 3, -1], $qr->project(['n'])->col(0)->toArray());
        try {
            $qr->project(['n'])->col('x');
            $this->fail('The "x" column should not be defined on the projection');
        }
        catch (UndefinedColumnException $e) {
        }
    }
}
