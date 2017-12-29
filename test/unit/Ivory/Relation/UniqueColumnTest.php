<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\IvoryTestCase;

class UniqueColumnTest extends IvoryTestCase
{
    /** @var IColumn */
    private $baseCol;

    protected function setUp()
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
        $this->assertSame(6, $this->baseCol->count());
        $this->assertSame(3, $this->baseCol->uniq()->count());
        $this->assertSame(3, $this->baseCol->uniq(1)->count());

        $parity = function ($value) { return $value % 2; };
        $this->assertSame(3, $this->baseCol->uniq($parity)->count());

        $parityComp = function ($a, $b) { return $a % 2 == $b % 2; };
        $this->assertSame(2, $this->baseCol->uniq($parity, $parityComp)->count());
        $this->assertSame(2, $this->baseCol->uniq(1, $parityComp)->count());
    }

    public function testRename()
    {
        $uniq = $this->baseCol->uniq();
        $renamed = $uniq->renameTo('x');

        $this->assertSame('a', $uniq->getName());
        $this->assertSame('x', $renamed->getName());
        $this->assertSame(3, $renamed->count());
    }

    public function testValues()
    {
        $this->assertSame([4, 1, 5], $this->baseCol->uniq()->toArray());

        $parity = function ($value) { return $value % 2; };
        $this->assertSame([4, 1, 5], $this->baseCol->uniq($parity)->toArray());

        $parityComp = function ($a, $b) { return $a % 2 == $b % 2; };
        $this->assertSame([4, 1], $this->baseCol->uniq(1, $parityComp)->toArray());

        $parityUniqued = $this->baseCol->uniq($parity, $parityComp);
        $this->assertSame([4, 1], $parityUniqued->toArray());
        $this->assertSame([4, 1], iterator_to_array($parityUniqued));
        $this->assertSame(4, $parityUniqued->value(0));
        $this->assertSame(1, $parityUniqued->value(1));
        $this->assertSame(1, $parityUniqued->value(-1));
        $this->assertSame(4, $parityUniqued->value(-2));

        try {
            $parityUniqued->value(2);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        try {
            $parityUniqued->value(-3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }
    }
}
