<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\IvoryTestCase;

class UniqueColumnTest extends IvoryTestCase
{
    /** @var IColumn */
    private $baseCol;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            'SELECT *
             FROM (VALUES (4, 3), (1, 2), (5, 6), (4, 4), (4, 8), (1, 1)) v (a, b)'
        );
        $this->baseCol = $rel->col(0);
    }

    public function testCount()
    {
        self::assertSame(6, $this->baseCol->count());
        self::assertSame(3, $this->baseCol->uniq()->count());
        self::assertSame(3, $this->baseCol->uniq(1)->count());

        $parity = function ($value) { return $value % 2; };
        self::assertSame(3, $this->baseCol->uniq($parity)->count());

        $parityComp = function ($a, $b) { return $a % 2 == $b % 2; };
        self::assertSame(2, $this->baseCol->uniq($parity, $parityComp)->count());
        self::assertSame(2, $this->baseCol->uniq(1, $parityComp)->count());
    }

    public function testRename()
    {
        $uniq = $this->baseCol->uniq();
        $renamed = $uniq->renameTo('x');

        self::assertSame('a', $uniq->getName());
        self::assertSame('x', $renamed->getName());
        self::assertSame(3, $renamed->count());
    }

    public function testValues()
    {
        self::assertSame([4, 1, 5], $this->baseCol->uniq()->toArray());

        $parity = function ($value) { return $value % 2; };
        self::assertSame([4, 1, 5], $this->baseCol->uniq($parity)->toArray());

        $parityComp = function ($a, $b) { return $a % 2 == $b % 2; };
        self::assertSame([4, 1], $this->baseCol->uniq(1, $parityComp)->toArray());

        $parityUniqued = $this->baseCol->uniq($parity, $parityComp);
        self::assertSame([4, 1], $parityUniqued->toArray());
        self::assertSame([4, 1], iterator_to_array($parityUniqued));
        self::assertSame(4, $parityUniqued->value(0));
        self::assertSame(1, $parityUniqued->value(1));
        self::assertSame(1, $parityUniqued->value(-1));
        self::assertSame(4, $parityUniqued->value(-2));

        try {
            $parityUniqued->value(2);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }

        try {
            $parityUniqued->value(-3);
            self::fail();
        } catch (\OutOfBoundsException $e) {
        }
    }
}
