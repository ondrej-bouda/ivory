<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;
use Ivory\IvoryTestCase;

class ProjectedRelationTest extends IvoryTestCase
{
    public function testSingleColSpec()
    {
        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            "SELECT 'abc' AS foo, 1, 42 AS the_answer,
                    7 AS a, 8 AS b, 'John' AS person_firstname, 'Doe' AS person_lastname"
        );
        self::assertSame([['a' => 7, 'b' => 8]], $rel->project(['a', 'b'])->toArray());
        self::assertSame([7, 8], $rel->project(['a', 'b'])->tuple()->toList());
        self::assertSame([['a' => 8, 'b' => 7]], $rel->project(['a' => 'b', 'b' => 'a'])->toArray());
        self::assertSame([['the_answer' => 42, 'x' => 'abc']], $rel->project([1, 2, 'x' => 0])->toArray());
        self::assertSame(
            [['sum' => 15]],
            $rel->project(['sum' => function (ITuple $t) { return $t[3] + $t->b; }])->toArray()
        );

        try {
            $rel->project(['c']);
            self::fail();
        } catch (UndefinedColumnException $e) {
        }

        try {
            $rel->project([-1]);
            self::fail();
        } catch (UndefinedColumnException $e) {
        }

        try {
            $rel->project([8]);
            self::fail();
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
        self::assertSame(
            [['p_firstname' => 'John', 'p_lastname' => 'Doe']],
            $rel->project(['p_*' => 'person_*'])->toArray()
        );
        self::assertSame(
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
        self::assertSame(
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

        self::assertSame([1, 3, 3, -1], $rel->col('n')->toArray());
        self::assertSame([1, 3, 3, -1], $rel->col(1)->toArray());
        self::assertSame(['a', 'b', 'h', 'w'], $rel->col('x')->toArray());

        self::assertSame([1, 3, 3, -1], $rel->project(['n'])->col('n')->toArray());
        self::assertSame([1, 3, 3, -1], $rel->project(['n'])->col(0)->toArray());
        try {
            $rel->project(['n'])->col('x');
            self::fail('The "x" column should not be defined on the projection');
        } catch (UndefinedColumnException $e) {
        }
    }
}
