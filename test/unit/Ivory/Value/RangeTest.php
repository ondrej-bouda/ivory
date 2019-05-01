<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\UnsupportedException;
use Ivory\Value\Alg\IDiscreteStepper;
use PHPUnit\Framework\TestCase;

class RangeTest extends TestCase
{
    /** @var Range */
    private $empty;
    /** @var Range */
    private $finite;
    /** @var Range */
    private $infinite;

    protected function setUp()
    {
        parent::setUp();

        $this->empty = Range::empty();
        $this->finite = Range::fromBounds(1, 4);
        $this->infinite = Range::fromBounds(null, 4);
    }

    private function intRng($lower, $upper): Range
    {
        return Range::fromBounds($lower, $upper);
    }

    public function testEmpty()
    {
        self::assertTrue($this->empty->isEmpty());
        self::assertNull($this->empty->getBoundsSpec());
        self::assertNull($this->empty->getLower());
        self::assertNull($this->empty->getUpper());
        self::assertNull($this->empty->isLowerInc());
        self::assertNull($this->empty->isUpperInc());
        self::assertNull($this->empty->getBoundsSpec());
        self::assertNull($this->empty->toBounds('[]'));
    }

    public function testFromBounds()
    {
        self::assertTrue(
            Range::fromBounds(1, 4)
                ->equals($this->intRng(1, 4))
        );

        self::assertSame('[]', Range::fromBounds(1, 3, '[]')->getBoundsSpec());
        self::assertSame('(]', Range::fromBounds(null, 3, '[]')->getBoundsSpec());
        self::assertSame('[)', Range::fromBounds(1, null, '[)')->getBoundsSpec());
        self::assertSame('()', Range::fromBounds(1, 3, '()')->getBoundsSpec());
        self::assertSame('()', Range::fromBounds(null, null, '[]')->getBoundsSpec());

        self::assertSame('[]', Range::fromBounds(1, 3, true, true)->getBoundsSpec());
        self::assertSame('(]', Range::fromBounds(null, 3, true, true)->getBoundsSpec());
        self::assertSame('[)', Range::fromBounds(1, null, true, false)->getBoundsSpec());
        self::assertSame('()', Range::fromBounds(1, 3, false, false)->getBoundsSpec());
        self::assertSame('()', Range::fromBounds(null, null, true, true)->getBoundsSpec());

        self::assertTrue(Range::fromBounds(1, 1)->isEmpty());
        self::assertTrue(Range::fromBounds(1, 2, '()')->isEmpty());
        self::assertTrue(Range::fromBounds(5, 3)->isEmpty());

        self::assertFalse(Range::fromBounds(3, 3, '[]')->isEmpty());
    }

    public function testEquals()
    {
        self::assertTrue(
            Range::fromBounds(1, 4)
                ->equals($this->intRng(1, 4))
        );

        self::assertTrue(
            Range::fromBounds(1, 3, '[]')
                ->equals($this->intRng(1, 4))
        );

        self::assertTrue(
            Range::fromBounds(0, 3, '(]')
                ->equals($this->intRng(1, 4))
        );

        self::assertTrue(
            Range::fromBounds(0, 4, '()')
                ->equals($this->intRng(1, 4))
        );

        self::assertFalse(
            Range::fromBounds(1, 3, '[]')
                ->equals($this->intRng(1, 3))
        );
    }

    public function testBounds()
    {
        self::assertSame(1, $this->finite->getLower());
        self::assertTrue($this->finite->isLowerInc());

        self::assertSame(4, $this->finite->getUpper());
        self::assertFalse($this->finite->isUpperInc());

        self::assertSame(null, $this->infinite->getLower());
        self::assertFalse($this->infinite->isLowerInc());

        self::assertSame(4, $this->infinite->getUpper());
        self::assertFalse($this->infinite->isUpperInc());
    }

    public function testBoundsSpec()
    {
        self::assertSame('[)', $this->finite->getBoundsSpec());
        self::assertSame('()', $this->infinite->getBoundsSpec());
    }

    public function testIsSinglePoint()
    {
        self::assertFalse($this->empty->isSinglePoint());
        self::assertFalse($this->intRng(1, 4)->isSinglePoint());
        self::assertTrue($this->intRng(1, 2)->isSinglePoint());
        self::assertTrue($this->intRng(PHP_INT_MAX - 1, PHP_INT_MAX)->isSinglePoint());
        self::assertFalse($this->intRng(null, 1)->isSinglePoint());
        self::assertFalse($this->intRng(4, null)->isSinglePoint());
        self::assertFalse($this->intRng(null, null)->isSinglePoint());
    }

    public function testToBounds()
    {
        self::assertSame([1, 4], $this->finite->toBounds('[)'));
        self::assertSame([1, 3], $this->finite->toBounds('[]'));
        self::assertSame([0, 4], $this->finite->toBounds('()'));
        self::assertSame([0, 3], $this->finite->toBounds('(]'));

        self::assertSame([null, 4], $this->infinite->toBounds('[)'));
        self::assertSame([null, 3], $this->infinite->toBounds('[]'));
        self::assertSame([null, 4], $this->infinite->toBounds('()'));
        self::assertSame([null, 3], $this->infinite->toBounds('(]'));
    }

    public function testContainsElement()
    {
        self::assertNull($this->empty->containsElement(null));
        self::assertNull($this->intRng(1, 4)->containsElement(null));
        self::assertNull($this->intRng(null, null)->containsElement(null));

        self::assertFalse($this->intRng(1, 4)->containsElement(0));
        self::assertTrue($this->intRng(1, 4)->containsElement(1));
        self::assertTrue($this->intRng(1, 4)->containsElement(2));
        self::assertTrue($this->intRng(1, 4)->containsElement(3));
        self::assertFalse($this->intRng(1, 4)->containsElement(4));

        self::assertTrue($this->intRng(null, 4)->containsElement(-PHP_INT_MAX));
        self::assertTrue($this->intRng(null, 4)->containsElement(-10));
        self::assertTrue($this->intRng(null, 4)->containsElement(3));
        self::assertFalse($this->intRng(null, 4)->containsElement(4));
        self::assertFalse($this->intRng(null, 4)->containsElement(5));

        self::assertFalse($this->intRng(4, null)->containsElement(-10));
        self::assertFalse($this->intRng(4, null)->containsElement(3));
        self::assertTrue($this->intRng(4, null)->containsElement(4));
        self::assertTrue($this->intRng(4, null)->containsElement(5));
        self::assertTrue($this->intRng(4, null)->containsElement(PHP_INT_MAX));

        self::assertTrue($this->intRng(null, null)->containsElement(-PHP_INT_MAX));
        self::assertTrue($this->intRng(null, null)->containsElement(-4));
        self::assertTrue($this->intRng(null, null)->containsElement(4));
        self::assertTrue($this->intRng(null, null)->containsElement(PHP_INT_MAX));
    }

    public function testLeftOfElement()
    {
        self::assertNull($this->empty->leftOfElement(null));
        self::assertNull($this->intRng(1, 4)->leftOfElement(null));
        self::assertNull($this->intRng(null, null)->leftOfElement(null));

        self::assertFalse($this->intRng(2, 6)->leftOfElement(-PHP_INT_MAX));
        self::assertFalse($this->intRng(2, 6)->leftOfElement(1));
        self::assertFalse($this->intRng(2, 6)->leftOfElement(2));
        self::assertFalse($this->intRng(2, 6)->leftOfElement(5));
        self::assertTrue($this->intRng(2, 6)->leftOfElement(6));
        self::assertTrue($this->intRng(2, 6)->leftOfElement(7));
        self::assertTrue($this->intRng(2, 6)->leftOfElement(PHP_INT_MAX));

        self::assertFalse($this->intRng(null, 4)->leftOfElement(-PHP_INT_MAX));
        self::assertFalse($this->intRng(null, 4)->leftOfElement(3));
        self::assertTrue($this->intRng(null, 4)->leftOfElement(4));
        self::assertTrue($this->intRng(null, 4)->leftOfElement(5));
        self::assertTrue($this->intRng(null, 4)->leftOfElement(PHP_INT_MAX));

        self::assertFalse($this->intRng(2, null)->leftOfElement(0));
        self::assertFalse($this->intRng(2, null)->leftOfElement(10));
        self::assertFalse($this->intRng(2, null)->leftOfElement(PHP_INT_MAX));

        self::assertFalse($this->intRng(null, null)->leftOfElement(0));
        self::assertFalse($this->intRng(null, null)->leftOfElement(10));
        self::assertFalse($this->intRng(null, null)->leftOfElement(PHP_INT_MAX));
    }

    public function testRightOfElement()
    {
        self::assertNull($this->empty->rightOfElement(null));
        self::assertNull($this->intRng(1, 4)->rightOfElement(null));
        self::assertNull($this->intRng(null, null)->rightOfElement(null));

        self::assertTrue($this->intRng(2, 6)->rightOfElement(-PHP_INT_MAX));
        self::assertTrue($this->intRng(2, 6)->rightOfElement(1));
        self::assertFalse($this->intRng(2, 6)->rightOfElement(2));
        self::assertFalse($this->intRng(2, 6)->rightOfElement(5));
        self::assertFalse($this->intRng(2, 6)->rightOfElement(6));
        self::assertFalse($this->intRng(2, 6)->rightOfElement(7));
        self::assertFalse($this->intRng(2, 6)->rightOfElement(PHP_INT_MAX));

        self::assertTrue($this->intRng(4, null)->rightOfElement(-PHP_INT_MAX));
        self::assertTrue($this->intRng(4, null)->rightOfElement(3));
        self::assertFalse($this->intRng(4, null)->rightOfElement(4));
        self::assertFalse($this->intRng(4, null)->rightOfElement(5));
        self::assertFalse($this->intRng(4, null)->rightOfElement(PHP_INT_MAX));

        self::assertFalse($this->intRng(null, 2)->rightOfElement(0));
        self::assertFalse($this->intRng(null, 2)->rightOfElement(10));
        self::assertFalse($this->intRng(null, 2)->rightOfElement(PHP_INT_MAX));

        self::assertFalse($this->intRng(null, null)->rightOfElement(0));
        self::assertFalse($this->intRng(null, null)->rightOfElement(10));
        self::assertFalse($this->intRng(null, null)->rightOfElement(PHP_INT_MAX));
    }

    public function testContainsRange()
    {
        self::assertNull($this->empty->containsRange(null));
        self::assertNull($this->intRng(1, 4)->containsRange(null));
        self::assertNull($this->intRng(null, null)->containsRange(null));

        self::assertTrue($this->intRng(1, 4)->containsRange($this->empty));
        self::assertFalse($this->empty->containsRange($this->intRng(-4, 0)));
        self::assertTrue($this->empty->containsRange($this->empty));

        self::assertFalse($this->intRng(8, 14)->containsRange($this->intRng(10, 20)));
        self::assertTrue($this->intRng(1, 8)->containsRange($this->intRng(2, 5)));
        self::assertFalse($this->intRng(2, 5)->containsRange($this->intRng(1, 8)));
        self::assertTrue($this->intRng(null, -6)->containsRange($this->intRng(-7, -6)));
        self::assertFalse($this->intRng(null, 4)->containsRange($this->intRng(0, null)));
        self::assertTrue($this->intRng(null, null)->containsRange($this->intRng(5, 6)));
        self::assertFalse($this->intRng(null, 8)->containsRange($this->intRng(null, null)));
        self::assertTrue($this->intRng(null, null)->containsRange($this->intRng(null, null)));

        self::assertFalse($this->intRng(1, 8)->containsRange($this->intRng(9, 12)));
        self::assertFalse($this->intRng(1, 8)->containsRange($this->intRng(8, 10)));
        self::assertFalse($this->intRng(null, 8)->containsRange($this->intRng(10, null)));
        self::assertTrue($this->intRng(null, null)->containsRange($this->empty));
    }

    public function testContainedInRange()
    {
        self::assertNull($this->empty->containedInRange(null));
        self::assertNull($this->intRng(1, 4)->containedInRange(null));
        self::assertNull($this->intRng(null, null)->containedInRange(null));

        self::assertFalse($this->intRng(1, 4)->containedInRange($this->empty));
        self::assertTrue($this->empty->containedInRange($this->intRng(-4, 0)));
        self::assertTrue($this->empty->containedInRange($this->empty));

        self::assertFalse($this->intRng(8, 14)->containedInRange($this->intRng(10, 20)));
        self::assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(2, 5)));
        self::assertTrue($this->intRng(2, 5)->containedInRange($this->intRng(1, 8)));
        self::assertFalse($this->intRng(null, -6)->containedInRange($this->intRng(-7, -6)));
        self::assertFalse($this->intRng(null, 4)->containedInRange($this->intRng(0, null)));
        self::assertFalse($this->intRng(null, null)->containedInRange($this->intRng(5, 6)));
        self::assertTrue($this->intRng(null, 8)->containedInRange($this->intRng(null, null)));
        self::assertTrue($this->intRng(null, null)->containedInRange($this->intRng(null, null)));

        self::assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(9, 12)));
        self::assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(8, 10)));
        self::assertFalse($this->intRng(null, 8)->containedInRange($this->intRng(10, null)));
        self::assertFalse($this->intRng(null, null)->containedInRange($this->empty));
    }

    public function testOverlaps()
    {
        self::assertNull($this->empty->overlaps(null));
        self::assertNull($this->intRng(1, 4)->overlaps(null));
        self::assertNull($this->intRng(null, null)->overlaps(null));

        self::assertFalse($this->intRng(1, 4)->overlaps($this->empty));
        self::assertFalse($this->empty->overlaps($this->intRng(-4, 0)));
        self::assertFalse($this->empty->overlaps($this->empty));

        self::assertTrue($this->intRng(8, 14)->overlaps($this->intRng(10, 20)));
        self::assertTrue($this->intRng(1, 8)->overlaps($this->intRng(2, 5)));
        self::assertTrue($this->intRng(2, 5)->overlaps($this->intRng(1, 8)));
        self::assertTrue($this->intRng(null, -6)->overlaps($this->intRng(-7, -6)));
        self::assertTrue($this->intRng(null, 4)->overlaps($this->intRng(0, null)));
        self::assertTrue($this->intRng(null, null)->overlaps($this->intRng(5, 6)));
        self::assertTrue($this->intRng(null, 8)->overlaps($this->intRng(null, null)));
        self::assertTrue($this->intRng(null, null)->overlaps($this->intRng(null, null)));

        self::assertFalse($this->intRng(1, 8)->overlaps($this->intRng(9, 12)));
        self::assertFalse($this->intRng(1, 8)->overlaps($this->intRng(8, 10)));
        self::assertFalse($this->intRng(null, 8)->overlaps($this->intRng(10, null)));
        self::assertFalse($this->intRng(null, null)->overlaps($this->empty));
    }

    public function testIntersect()
    {
        self::assertNull($this->empty->intersect(null));
        self::assertNull($this->intRng(1, 4)->intersect(null));
        self::assertNull($this->intRng(null, null)->intersect(null));

        self::assertTrue($this->empty->equals($this->intRng(1, 4)->intersect($this->empty)));
        self::assertTrue($this->empty->equals($this->empty->intersect($this->intRng(-4, 0))));
        self::assertTrue($this->empty->equals($this->empty->intersect($this->empty)));

        self::assertTrue($this->intRng(10, 14)->equals($this->intRng(8, 14)->intersect($this->intRng(10, 20))));
        self::assertTrue($this->intRng(2, 5)->equals($this->intRng(1, 8)->intersect($this->intRng(2, 5))));
        self::assertTrue($this->intRng(2, 5)->equals($this->intRng(2, 5)->intersect($this->intRng(1, 8))));
        self::assertTrue($this->intRng(-7, -6)->equals($this->intRng(null, -6)->intersect($this->intRng(-7, -6))));
        self::assertTrue($this->intRng(0, 4)->equals($this->intRng(null, 4)->intersect($this->intRng(0, null))));
        self::assertTrue($this->intRng(5, 6)->equals($this->intRng(null, null)->intersect($this->intRng(5, 6))));
        self::assertTrue($this->intRng(null, 8)->equals($this->intRng(null, 8)->intersect($this->intRng(null, null))));
        self::assertTrue(
            $this->intRng(null, null)->equals($this->intRng(null, null)->intersect($this->intRng(null, null)))
        );

        self::assertTrue($this->empty->equals($this->intRng(1, 8)->intersect($this->intRng(9, 12))));
        self::assertTrue($this->empty->equals($this->intRng(1, 8)->intersect($this->intRng(8, 10))));
        self::assertTrue($this->empty->equals($this->intRng(null, 8)->intersect($this->intRng(10, null))));
        self::assertTrue($this->empty->equals($this->intRng(null, null)->intersect($this->empty)));
    }

    public function testIsFinite()
    {
        self::assertTrue($this->empty->isFinite());
        self::assertTrue($this->intRng(1, 2)->isFinite());
        self::assertTrue($this->intRng(-3, 5)->isFinite());

        self::assertFalse($this->intRng(0, null)->isFinite());
        self::assertFalse($this->intRng(null, -4)->isFinite());
        self::assertFalse($this->intRng(null, null)->isFinite());
    }

    public function testStrictlyLeftOf()
    {
        self::assertNull($this->empty->strictlyLeftOf(null));
        self::assertNull($this->intRng(1, 4)->strictlyLeftOf(null));
        self::assertNull($this->intRng(null, null)->strictlyLeftOf(null));

        self::assertFalse($this->empty->strictlyLeftOf($this->intRng(1, 4)));
        self::assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->empty));
        self::assertFalse($this->empty->strictlyLeftOf($this->empty));

        self::assertTrue($this->intRng(1, 4)->strictlyLeftOf($this->intRng(5, 9)));
        self::assertTrue($this->intRng(1, 4)->strictlyLeftOf($this->intRng(4, 9)));
        self::assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(3, 9)));
        self::assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 2)));
        self::assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 1)));
        self::assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 0)));

        self::assertTrue($this->intRng(null, 4)->strictlyLeftOf($this->intRng(5, 9)));
        self::assertTrue($this->intRng(null, 4)->strictlyLeftOf($this->intRng(4, 9)));
        self::assertFalse($this->intRng(null, 4)->strictlyLeftOf($this->intRng(3, 9)));

        self::assertFalse($this->intRng(null, 4)->strictlyLeftOf($this->intRng(null, 9)));
        self::assertFalse($this->intRng(null, null)->strictlyLeftOf($this->intRng(3, 9)));
    }

    public function testStrictlyRightOf()
    {
        self::assertNull($this->empty->strictlyRightOf(null));
        self::assertNull($this->intRng(1, 4)->strictlyRightOf(null));
        self::assertNull($this->intRng(null, null)->strictlyRightOf(null));

        self::assertFalse($this->empty->strictlyRightOf($this->intRng(1, 4)));
        self::assertFalse($this->intRng(1, 4)->strictlyRightOf($this->empty));
        self::assertFalse($this->empty->strictlyRightOf($this->empty));

        self::assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(5, 9)));
        self::assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(4, 9)));
        self::assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(3, 9)));
        self::assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 2)));
        self::assertTrue($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 1)));
        self::assertTrue($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 0)));

        self::assertTrue($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 3)));
        self::assertTrue($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 4)));
        self::assertFalse($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 5)));

        self::assertFalse($this->intRng(null, 4)->strictlyRightOf($this->intRng(null, 9)));
        self::assertFalse($this->intRng(null, null)->strictlyRightOf($this->intRng(3, 9)));
    }

    public function testIntRange()
    {
        $range = Range::fromBounds(5, 10); // range from 5 (inclusive) to 10 (exclusive)

        self::assertFalse($range->containsElement(4));
        self::assertTrue($range->containsElement(5));
        self::assertTrue($range->containsElement(7));
        self::assertTrue($range->containsElement(9));
        self::assertFalse($range->containsElement(10));

        self::assertSame([4, 9], $range->toBounds('(]'));
    }

    public function testDateRange()
    {
        $range = Range::fromBounds(
            Date::fromParts(2018, 1, 15),
            Date::fromParts(2018, 2, 1)
        );

        self::assertTrue(
            $range->containsElement(Date::fromParts(2018, 1, 31))
        );
        self::assertTrue(
            $range->containsRange(Range::fromBounds(Date::fromParts(2018, 1, 20), Date::fromParts(2018, 1, 31), '[]'))
        );

        self::assertEquals(
            [
                Date::fromParts(2018, 1, 14),
                Date::fromParts(2018, 1, 31)
            ],
            $range->toBounds('(]')
        );

        self::assertTrue(
            Range::fromBounds(Date::fromParts(2018, 1, 15), Date::fromParts(2018, 1, 16), '()')
                ->isEmpty()
        );

        self::assertEquals(
            [Date::fromParts(2018, 1, 14), Date::fromParts(2018, 2, 2)],
            Range::fromBounds(Date::fromParts(2018, 1, 15), Date::fromParts(2018, 2, 3))->toBounds('(]')
        );
    }

    public function testFloatRange()
    {
        $range = Range::fromBounds(1.1, 4.2);

        self::assertFalse($range->containsElement(1.00000009));
        self::assertTrue($range->containsElement(1.1));
        self::assertTrue($range->containsElement(4));
        self::assertTrue($range->containsElement(4.19));
        self::assertFalse($range->containsElement(4.2));

        try {
            $range->toBounds('[]');
            self::fail(UnsupportedException::class . ' expected due to the subtype being continuous');
        } catch (UnsupportedException $e) {
        }
    }

    public function testCustomDiscreteStepper()
    {
        $decimalStepper = new class implements IDiscreteStepper
        {
            public function step(int $delta, $value)
            {
                if ($delta == 1) {
                    return (floor($value * 10) + 1) / 10;
                } elseif ($delta == -1) {
                    return (ceil($value * 10) - 1) / 10;
                } else {
                    throw new \InvalidArgumentException();
                }
            }
        };

        $range = Range::fromBounds(1.1, 4.25, '[]', null, null, $decimalStepper);
        self::assertSame([1.0, 4.3], $range->toBounds('()'));
    }
}
