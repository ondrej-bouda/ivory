<?php
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;

class ProjectedRelationTest extends \Ivory\IvoryTestCase
{
    public function testSingleColSpec()
    {
        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            "SELECT 'abc' AS foo, 1, 42 AS the_answer,
                    7 AS a, 8 AS b, 'John' AS person_firstname, 'Doe' AS person_lastname,
                    100 AS a"
        );
        $this->assertSame([['a' => 7, 'b' => 8]], $rel->project(['a', 'b'])->toArray());
        $this->assertSame([7, 8], $rel->project(['a', 'b'])->tuple()->toList());
        $this->assertSame([['a' => 8, 'b' => 7]], $rel->project(['a' => 'b', 'b' => 'a'])->toArray());
        $this->assertSame([['the_answer' => 42, 'x' => 'abc']], $rel->project([1, 2, 'x' => 0])->toArray());
        $this->assertSame(
            [['sum' => 15]],
            $rel->project(['sum' => function (ITuple $t) { return $t[3] + $t->b; }])->toArray()
        );

        try {
            $rel->project(['c']);
            $this->fail();
        } catch (UndefinedColumnException $e) {
        }

        try {
            $rel->project([-1]);
            $this->fail();
        } catch (UndefinedColumnException $e) {
        }

        try {
            $rel->project([8]);
            $this->fail();
        } catch (UndefinedColumnException $e) {
        }
    }

    public function testMultiColSpec()
    {
        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            "SELECT 'abc' AS foo, 1, 42 AS the_answer,
                    7 AS a, 8 AS b, 'John' AS person_firstname, 'Doe' AS person_lastname"
        );
        $this->assertSame(
            [['p_firstname' => 'John', 'p_lastname' => 'Doe']],
            $rel->project(['p_*' => 'person_*'])->toArray()
        );
        $this->assertSame(
            [
                [
                    'foo' => 'abc',
                    'the_answer' => 42,
                    'a' => 7,
                    'b' => 8,
                    'person_firstname' => 'John',
                    'person_lastname' => 'Doe',
                    'copy' => 7,
                ],
            ],
            $rel->project(['*', 'copy' => 'a'])->toArray()
        );
        $this->assertSame(
            [
                [
                    'a' => 7,
                    'the_answer' => 42,
                    'person_firstname' => 'John',
                    'person_first' => 'John',
                    'person_last' => 'Doe',
                ],
            ],
            $rel->project(['a', '/_.*R/i', '\1' => '/(.*)name$/'])->toArray()
        );

        try {
            $rel->project(['q*']);
        } catch (UndefinedColumnException $e) {
        }

        try {
            $rel->project(['/.{255}/']);
        } catch (UndefinedColumnException $e) {
        }
    }

    public function testColumnValues()
    {
        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            "SELECT *
             FROM (VALUES ('a', 1), ('b', 3), ('h', 3), ('w', -1)) v (x, n)"
        );

        $this->assertSame([1, 3, 3, -1], $rel->col('n')->toArray());
        $this->assertSame([1, 3, 3, -1], $rel->col(1)->toArray());
        $this->assertSame(['a', 'b', 'h', 'w'], $rel->col('x')->toArray());

        $this->assertSame([1, 3, 3, -1], $rel->project(['n'])->col('n')->toArray());
        $this->assertSame([1, 3, 3, -1], $rel->project(['n'])->col(0)->toArray());
        try {
            $rel->project(['n'])->col('x');
            $this->fail('The "x" column should not be defined on the projection');
        } catch (UndefinedColumnException $e) {
        }
    }
}
