<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\IvoryTestCase;
use Ivory\Value\Alg\IValueFilter;

class FilteredColumnTest extends IvoryTestCase
{
    /** @var IColumn */
    private $baseCol;

    protected function setUp(): void
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
        self::assertSame(3, $this->baseCol->count());
        self::assertSame(3, $this->baseCol->filter(function () { return true; })->count());
        self::assertSame(2, $this->baseCol->filter(function ($n) { return ($n % 2 == 1); })->count());
        self::assertSame(2, $this->baseCol->filter(function ($n) { return $n % 2; })->count());
        self::assertSame(1, $this->baseCol->filter(function ($n) { return ($n > 4); })->count());
        self::assertSame(0, $this->baseCol->filter(function ($n) { return ($n > 5); })->count());
    }

    public function testRename()
    {
        $filtered = $this->baseCol->filter(function ($n) { return $n % 2; });
        $renamed = $filtered->renameTo('x');

        self::assertSame('a', $filtered->getName());
        self::assertSame('x', $renamed->getName());
        self::assertSame(2, $renamed->count());
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

        self::assertSame([5], $filtered->toArray());
        self::assertSame([1, 5], $decider->getDecided());
    }

    public function testValues()
    {
        $filtered = $this->baseCol->filter(function ($n) { return $n % 2; });
        self::assertSame([1, 5], $filtered->toArray());
        self::assertSame([1, 5], iterator_to_array($filtered));
        self::assertSame(1, $filtered->value(0));
        self::assertSame(5, $filtered->value(1));
        self::assertSame(5, $filtered->value(-1));
        self::assertSame(1, $filtered->value(-2));

        try {
            $filtered->value(2);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        try {
            $filtered->value(-3);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }
    }
}
