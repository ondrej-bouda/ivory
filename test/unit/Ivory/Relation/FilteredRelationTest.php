<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\IConnection;
use Ivory\IvoryTestCase;
use Ivory\Value\Alg\ITupleFilter;

class FilteredRelationTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testClosure()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (1, 2), (4, 3), (5, 6)) v (a, b)'
        );
        $filtered = $rel->filter(function (ITuple $tuple) {
            return ($tuple->a < $tuple->b);
        });

        self::assertSame(2, $filtered->count());

        self::assertSame([1, 2], $filtered->tuple(0)->toList());
        self::assertSame([5, 6], $filtered->tuple(1)->toList());

        self::assertSame([1, 2], $filtered->tuple(-2)->toList());
        self::assertSame([5, 6], $filtered->tuple(-1)->toList());

        $i = 0;
        $expected = [[1, 2], [5, 6]];
        foreach ($filtered as $k => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($i, $k, "tuple $i");
            self::assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        self::assertSame(2, $i);

        $col = $filtered->col('a');
        self::assertSame(5, $col->value(1));
        self::assertSame([1, 5], $col->toArray());
    }

    public function testTupleFilter()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (1, 2), (4, 3), (5, 6)) v (a, b)'
        );

        $filterMod3 = new class implements ITupleFilter {
            public function accept(ITuple $tuple): bool
            {
                return ($tuple->b % 3 == 0);
            }
        };
        $filtered = $rel->filter($filterMod3);
        self::assertSame(
            [
                ['a' => 4, 'b' => 3],
                ['a' => 5, 'b' => 6],
            ],
            $filtered->toArray()
        );
    }
}
