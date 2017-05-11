<?php
namespace Ivory\Relation;

class TupleTest extends \PHPUnit\Framework\TestCase
{
    /** @var Tuple */
    private $simpleTuple;
    /** @var Tuple */
    private $complexTuple;

    protected function setUp()
    {
        parent::setUp();

        $this->simpleTuple = Tuple::fromMap([
            'a' => 1,
            'b' => 2,
            'c' => null,
        ]);

        $this->complexTuple = new Tuple(
            [1, 3, null, 4],
            ['a', 'b', null, 'a'],
            ['a' => 0, 'b' => 1]
        );
    }

    public function testToMap()
    {
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => null], $this->simpleTuple->toMap());
        $this->assertSame(['a' => 1, 'b' => 3], $this->complexTuple->toMap());
    }

    public function testToList()
    {
        $this->assertSame([1, 2, null], $this->simpleTuple->toList());
        $this->assertSame([1, 3, null, 4], $this->complexTuple->toList());
    }

    public function testIteration()
    {
        $expKeys = ['a', 'b', 'c'];
        $expVals = [1, 2, null];
        $i = 0;
        foreach ($this->simpleTuple as $k => $v) {
            $this->assertSame($expKeys[$i], $k, "Key $i");
            $this->assertSame($expVals[$i], $v, "Value $i");
            $i++;
        }

        $expKeys = ['a', 'b', null, 'a'];
        $expVals = [1, 3, null, 4];
        $i = 0;
        foreach ($this->complexTuple as $k => $v) {
            $this->assertSame($expKeys[$i], $k, "Key $i");
            $this->assertSame($expVals[$i], $v, "Value $i");
            $i++;
        }
    }
}
