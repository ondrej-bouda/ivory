<?php
namespace Ivory\Relation;

use Ivory\Relation\Alg\IValueFilter;

class FilteredColumnTest extends \Ivory\IvoryTestCase
{
    /** @var IColumn */
    private $baseCol;

    protected function setUp()
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            'SELECT *
             FROM (VALUES (1, 2), (4, 3), (5, 6)) v (a, b)'
        );
        $this->baseCol = $rel->col(0);
    }

    public function testCount()
    {
        $this->assertSame(3, $this->baseCol->count());
        $this->assertSame(3, $this->baseCol->filter(function ($n) { return true; })->count());
        $this->assertSame(2, $this->baseCol->filter(function ($n) { return ($n % 2 == 1); })->count());
        $this->assertSame(2, $this->baseCol->filter(function ($n) { return $n % 2; })->count());
        $this->assertSame(1, $this->baseCol->filter(function ($n) { return ($n > 4); })->count());
        $this->assertSame(0, $this->baseCol->filter(function ($n) { return ($n > 5); })->count());
    }

    public function testRename()
    {
        $filtered = $this->baseCol->filter(function ($n) { return $n % 2; });
        $renamed = $filtered->renameTo('x');

        $this->assertSame('a', $filtered->getName());
        $this->assertSame('x', $renamed->getName());
        $this->assertSame(2, $renamed->count());
    }

    public function testChaining()
    {
        $decider = new class implements IValueFilter {
            private $decided = [];

            public function accept($value): bool
            {
                $this->decided[] = $value;
                return ($value > 4);
            }

            public function getDecided()
            {
                return $this->decided;
            }
        };
        $filtered = $this->baseCol->filter(function ($n) { return $n % 2; })->filter($decider);

        $this->assertSame([5], $filtered->toArray());
        $this->assertSame([1, 5], $decider->getDecided());
    }

    public function testValues()
    {
        $filtered = $this->baseCol->filter(function ($n) { return $n % 2; });
        $this->assertSame([1, 5], $filtered->toArray());
        $this->assertSame([1, 5], iterator_to_array($filtered));
        $this->assertSame(1, $filtered->value(0));
        $this->assertSame(5, $filtered->value(1));
        $this->assertSame(5, $filtered->value(-1));
        $this->assertSame(1, $filtered->value(-2));

        try {
            $filtered->value(2);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        try {
            $filtered->value(-3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }
    }
}
