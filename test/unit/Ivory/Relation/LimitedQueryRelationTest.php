<?php
namespace Ivory\Relation;

class LimitedRelationTest extends \Ivory\IvoryTestCase
{
    public function testLimitFirstOne()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'VALUES (1,2), (3,4), (3,6), (7,8)'
        );
        $limited = $qr->limit(1);

        $this->assertSame(1, $limited->count());

        $this->assertSame([1, 2], $limited->tuple(0)->toList());
        try {
            $limited->tuple(1);
            $this->fail();
        }
        catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        foreach ($limited as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame([1, 2], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(1, $i);
    }

    public function testLimitFirstThree()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $qr->limit(3);

        $this->assertSame(3, $limited->count());

        $this->assertSame([1, 2], $limited->tuple(0)->toList());
        $this->assertSame([3, 4], $limited->tuple(1)->toList());
        $this->assertSame([5, 6], $limited->tuple(2)->toList());
        try {
            $limited->tuple(3);
            $this->fail();
        }
        catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[1, 2], [3, 4], [5, 6]];
        foreach ($limited as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(3, $i);
    }

    public function testLimitTwoOffsetOne()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $qr->limit(2, 1);

        $this->assertSame(2, $limited->count());

        $this->assertSame([3, 4], $limited->tuple(0)->toList());
        $this->assertSame([5, 6], $limited->tuple(1)->toList());
        try {
            $limited->tuple(2);
            $this->fail();
        }
        catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6]];
        foreach ($limited as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(2, $i);
    }

    public function testOffsetOneUnlimited()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'VALUES (1,2), (3,4), (5,6), (7,8)'
        );
        $limited = $qr->limit(null, 1);

        $this->assertSame(3, $limited->count());

        $this->assertSame([3, 4], $limited->tuple(0)->toList());
        $this->assertSame([5, 6], $limited->tuple(1)->toList());
        $this->assertSame([7, 8], $limited->tuple(2)->toList());
        try {
            $limited->tuple(3);
            $this->fail();
        }
        catch (\OutOfBoundsException $e) {
        }

        $i = 0;
        $expected = [[3, 4], [5, 6], [7, 8]];
        foreach ($limited as $k => $tuple) {
            /** @var ITuple $tuple */
            $this->assertSame($i, $k, "tuple $i");
            $this->assertSame($expected[$i], $tuple->toList(), "tuple $i");
            $i++;
        }
        $this->assertSame(3, $i);
    }
}
