<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\ITupleFilter;

class FilteredRelationTest extends \Ivory\IvoryTestCase
{
    public function testClosure()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'SELECT *
             FROM (VALUES (1, 2), (4, 3), (5, 6)) v (a, b)'
        );
        $filtered = $qr->filter(function (ITuple $tuple) {
            return ($tuple['a'] < $tuple['b']);
        });

        $this->assertSame(2, $filtered->count());

        $this->assertSame([1, 2], $filtered->tuple(0)->toList());
        $this->assertSame([5, 6], $filtered->tuple(1)->toList());

        $this->assertSame([1, 2], $filtered->tuple(-2)->toList());
        $this->assertSame([5, 6], $filtered->tuple(-1)->toList());

        $i = 0;
        $expected = [[1, 2], [5, 6]];
        foreach ($filtered as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(2, $i);

        $col = $filtered->col('a');
        $this->assertSame(5, $col->value(1));
        $this->assertSame([1, 5], $col->toArray());
    }

    public function testTupleFilter()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'SELECT *
             FROM (VALUES (1, 2), (4, 3), (5, 6)) v (a, b)'
        );
        $filteredMod3 = $qr->filter(new FilteredRelationTestTupleFilter(3));
        $this->assertSame(2, $filteredMod3->count());
        $this->assertSame([4, 3], $filteredMod3->tuple(0)->toList());
        $this->assertSame([5, 6], $filteredMod3->tuple(1)->toList());

        $filteredMod6 = $qr->filter(new FilteredRelationTestTupleFilter(6));
        $this->assertSame(1, $filteredMod6->count());
        $this->assertSame([5, 6], $filteredMod6->tuple(0)->toList());
    }
}

class FilteredRelationTestTupleFilter implements ITupleFilter
{
    private $mod;

    public function __construct($mod)
    {
        $this->mod = $mod;
    }

    public function accept(ITuple $tuple)
    {
        return ($tuple['b'] % $this->mod == 0);
    }
}
