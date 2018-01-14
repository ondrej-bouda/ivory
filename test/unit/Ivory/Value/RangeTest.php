<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\Std\IntegerType;

class RangeTest extends \PHPUnit\Framework\TestCase
{
    /** @var ITotallyOrderedType */
    private $intType;
    /** @var Range */
    private $empty;
    /** @var Range */
    private $finite;
    /** @var Range */
    private $infinite;

    protected function setUp()
    {
        parent::setUp();

        $this->intType = new IntegerType('pg_catalog', 'int4');

        $this->empty = Range::createEmpty($this->intType);
        $this->finite = Range::createFromBounds($this->intType, 1, 4);
        $this->infinite = Range::createFromBounds($this->intType, null, 4);
    }

    private function intRng($lower, $upper)
    {
        return Range::createFromBounds(
            $this->intType,
            $lower,
            $upper
        );
    }

    public function testEmpty()
    {
        $this->assertTrue($this->empty->isEmpty());
        $this->assertNull($this->empty->getBoundsSpec());
        $this->assertNull($this->empty->getLower());
        $this->assertNull($this->empty->getUpper());
        $this->assertNull($this->empty->isLowerInc());
        $this->assertNull($this->empty->isUpperInc());
        $this->assertNull($this->empty->getBoundsSpec());
        $this->assertNull($this->empty->toBounds('[]'));
    }

    public function testCreateFromBounds()
    {
        $this->assertTrue(
            Range::createFromBounds($this->intType, 1, 4)
                ->equals($this->intRng(1, 4))
        );

        $this->assertSame('[]', Range::createFromBounds($this->intType, 1, 3, '[]')->getBoundsSpec());
        $this->assertSame('(]', Range::createFromBounds($this->intType, null, 3, '[]')->getBoundsSpec());
        $this->assertSame('[)', Range::createFromBounds($this->intType, 1, null, '[)')->getBoundsSpec());
        $this->assertSame('()', Range::createFromBounds($this->intType, 1, 3, '()')->getBoundsSpec());
        $this->assertSame('()', Range::createFromBounds($this->intType, null, null, '[]')->getBoundsSpec());

        $this->assertSame('[]', Range::createFromBounds($this->intType, 1, 3, true, true)->getBoundsSpec());
        $this->assertSame('(]', Range::createFromBounds($this->intType, null, 3, true, true)->getBoundsSpec());
        $this->assertSame('[)', Range::createFromBounds($this->intType, 1, null, true, false)->getBoundsSpec());
        $this->assertSame('()', Range::createFromBounds($this->intType, 1, 3, false, false)->getBoundsSpec());
        $this->assertSame('()', Range::createFromBounds($this->intType, null, null, true, true)->getBoundsSpec());

        $this->assertTrue(Range::createFromBounds($this->intType, 1, 1)->isEmpty());
        $this->assertTrue(Range::createFromBounds($this->intType, 1, 2, '()')->isEmpty());
        $this->assertTrue(Range::createFromBounds($this->intType, 5, 3)->isEmpty());

        $this->assertFalse(Range::createFromBounds($this->intType, 3, 3, '[]')->isEmpty());
    }

    public function testEquals()
    {
        $this->assertTrue(
            Range::createFromBounds($this->intType, 1, 4)
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, 1, 3, '[]')
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, 0, 3, '(]')
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, 0, 4, '()')
                ->equals($this->intRng(1, 4))
        );

        $this->assertFalse(
            Range::createFromBounds($this->intType, 1, 3, '[]')
                ->equals($this->intRng(1, 3))
        );
    }

    public function testBounds()
    {
        $this->assertSame(1, $this->finite->getLower());
        $this->assertTrue($this->finite->isLowerInc());

        $this->assertSame(4, $this->finite->getUpper());
        $this->assertFalse($this->finite->isUpperInc());

        $this->assertSame(null, $this->infinite->getLower());
        $this->assertFalse($this->infinite->isLowerInc());

        $this->assertSame(4, $this->infinite->getUpper());
        $this->assertFalse($this->infinite->isUpperInc());
    }

    public function testBoundsSpec()
    {
        $this->assertSame('[)', $this->finite->getBoundsSpec());
        $this->assertSame('()', $this->infinite->getBoundsSpec());
    }

    public function testIsSinglePoint()
    {
        $this->assertFalse($this->empty->isSinglePoint());
        $this->assertFalse($this->intRng(1, 4)->isSinglePoint());
        $this->assertTrue($this->intRng(1, 2)->isSinglePoint());
        $this->assertTrue($this->intRng(PHP_INT_MAX - 1, PHP_INT_MAX)->isSinglePoint());
        $this->assertFalse($this->intRng(null, 1)->isSinglePoint());
        $this->assertFalse($this->intRng(4, null)->isSinglePoint());
        $this->assertFalse($this->intRng(null, null)->isSinglePoint());
    }

    public function testToBounds()
    {
        $this->assertSame([1, 4], $this->finite->toBounds('[)'));
        $this->assertSame([1, 3], $this->finite->toBounds('[]'));
        $this->assertSame([0, 4], $this->finite->toBounds('()'));
        $this->assertSame([0, 3], $this->finite->toBounds('(]'));

        $this->assertSame([null, 4], $this->infinite->toBounds('[)'));
        $this->assertSame([null, 3], $this->infinite->toBounds('[]'));
        $this->assertSame([null, 4], $this->infinite->toBounds('()'));
        $this->assertSame([null, 3], $this->infinite->toBounds('(]'));
    }

    public function testContainsElement()
    {
        $this->assertNull($this->empty->containsElement(null));
        $this->assertNull($this->intRng(1, 4)->containsElement(null));
        $this->assertNull($this->intRng(null, null)->containsElement(null));

        $this->assertFalse($this->intRng(1, 4)->containsElement(0));
        $this->assertTrue($this->intRng(1, 4)->containsElement(1));
        $this->assertTrue($this->intRng(1, 4)->containsElement(2));
        $this->assertTrue($this->intRng(1, 4)->containsElement(3));
        $this->assertFalse($this->intRng(1, 4)->containsElement(4));

        $this->assertTrue($this->intRng(null, 4)->containsElement(-PHP_INT_MAX));
        $this->assertTrue($this->intRng(null, 4)->containsElement(-10));
        $this->assertTrue($this->intRng(null, 4)->containsElement(3));
        $this->assertFalse($this->intRng(null, 4)->containsElement(4));
        $this->assertFalse($this->intRng(null, 4)->containsElement(5));

        $this->assertFalse($this->intRng(4, null)->containsElement(-10));
        $this->assertFalse($this->intRng(4, null)->containsElement(3));
        $this->assertTrue($this->intRng(4, null)->containsElement(4));
        $this->assertTrue($this->intRng(4, null)->containsElement(5));
        $this->assertTrue($this->intRng(4, null)->containsElement(PHP_INT_MAX));

        $this->assertTrue($this->intRng(null, null)->containsElement(-PHP_INT_MAX));
        $this->assertTrue($this->intRng(null, null)->containsElement(-4));
        $this->assertTrue($this->intRng(null, null)->containsElement(4));
        $this->assertTrue($this->intRng(null, null)->containsElement(PHP_INT_MAX));
    }

    public function testLeftOfElement()
    {
        $this->assertNull($this->empty->leftOfElement(null));
        $this->assertNull($this->intRng(1, 4)->leftOfElement(null));
        $this->assertNull($this->intRng(null, null)->leftOfElement(null));

        $this->assertFalse($this->intRng(2, 6)->leftOfElement(-PHP_INT_MAX));
        $this->assertFalse($this->intRng(2, 6)->leftOfElement(1));
        $this->assertFalse($this->intRng(2, 6)->leftOfElement(2));
        $this->assertFalse($this->intRng(2, 6)->leftOfElement(5));
        $this->assertTrue($this->intRng(2, 6)->leftOfElement(6));
        $this->assertTrue($this->intRng(2, 6)->leftOfElement(7));
        $this->assertTrue($this->intRng(2, 6)->leftOfElement(PHP_INT_MAX));

        $this->assertFalse($this->intRng(null, 4)->leftOfElement(-PHP_INT_MAX));
        $this->assertFalse($this->intRng(null, 4)->leftOfElement(3));
        $this->assertTrue($this->intRng(null, 4)->leftOfElement(4));
        $this->assertTrue($this->intRng(null, 4)->leftOfElement(5));
        $this->assertTrue($this->intRng(null, 4)->leftOfElement(PHP_INT_MAX));

        $this->assertFalse($this->intRng(2, null)->leftOfElement(0));
        $this->assertFalse($this->intRng(2, null)->leftOfElement(10));
        $this->assertFalse($this->intRng(2, null)->leftOfElement(PHP_INT_MAX));

        $this->assertFalse($this->intRng(null, null)->leftOfElement(0));
        $this->assertFalse($this->intRng(null, null)->leftOfElement(10));
        $this->assertFalse($this->intRng(null, null)->leftOfElement(PHP_INT_MAX));
    }

    public function testRightOfElement()
    {
        $this->assertNull($this->empty->rightOfElement(null));
        $this->assertNull($this->intRng(1, 4)->rightOfElement(null));
        $this->assertNull($this->intRng(null, null)->rightOfElement(null));

        $this->assertTrue($this->intRng(2, 6)->rightOfElement(-PHP_INT_MAX));
        $this->assertTrue($this->intRng(2, 6)->rightOfElement(1));
        $this->assertFalse($this->intRng(2, 6)->rightOfElement(2));
        $this->assertFalse($this->intRng(2, 6)->rightOfElement(5));
        $this->assertFalse($this->intRng(2, 6)->rightOfElement(6));
        $this->assertFalse($this->intRng(2, 6)->rightOfElement(7));
        $this->assertFalse($this->intRng(2, 6)->rightOfElement(PHP_INT_MAX));

        $this->assertTrue($this->intRng(4, null)->rightOfElement(-PHP_INT_MAX));
        $this->assertTrue($this->intRng(4, null)->rightOfElement(3));
        $this->assertFalse($this->intRng(4, null)->rightOfElement(4));
        $this->assertFalse($this->intRng(4, null)->rightOfElement(5));
        $this->assertFalse($this->intRng(4, null)->rightOfElement(PHP_INT_MAX));

        $this->assertFalse($this->intRng(null, 2)->rightOfElement(0));
        $this->assertFalse($this->intRng(null, 2)->rightOfElement(10));
        $this->assertFalse($this->intRng(null, 2)->rightOfElement(PHP_INT_MAX));

        $this->assertFalse($this->intRng(null, null)->rightOfElement(0));
        $this->assertFalse($this->intRng(null, null)->rightOfElement(10));
        $this->assertFalse($this->intRng(null, null)->rightOfElement(PHP_INT_MAX));
    }

    public function testContainsRange()
    {
        $this->assertNull($this->empty->containsRange(null));
        $this->assertNull($this->intRng(1, 4)->containsRange(null));
        $this->assertNull($this->intRng(null, null)->containsRange(null));

        $this->assertTrue($this->intRng(1, 4)->containsRange($this->empty));
        $this->assertFalse($this->empty->containsRange($this->intRng(-4, 0)));
        $this->assertTrue($this->empty->containsRange($this->empty));

        $this->assertFalse($this->intRng(8, 14)->containsRange($this->intRng(10, 20)));
        $this->assertTrue($this->intRng(1, 8)->containsRange($this->intRng(2, 5)));
        $this->assertFalse($this->intRng(2, 5)->containsRange($this->intRng(1, 8)));
        $this->assertTrue($this->intRng(null, -6)->containsRange($this->intRng(-7, -6)));
        $this->assertFalse($this->intRng(null, 4)->containsRange($this->intRng(0, null)));
        $this->assertTrue($this->intRng(null, null)->containsRange($this->intRng(5, 6)));
        $this->assertFalse($this->intRng(null, 8)->containsRange($this->intRng(null, null)));
        $this->assertTrue($this->intRng(null, null)->containsRange($this->intRng(null, null)));

        $this->assertFalse($this->intRng(1, 8)->containsRange($this->intRng(9, 12)));
        $this->assertFalse($this->intRng(1, 8)->containsRange($this->intRng(8, 10)));
        $this->assertFalse($this->intRng(null, 8)->containsRange($this->intRng(10, null)));
        $this->assertTrue($this->intRng(null, null)->containsRange($this->empty));
    }

    public function testContainedInRange()
    {
        $this->assertNull($this->empty->containedInRange(null));
        $this->assertNull($this->intRng(1, 4)->containedInRange(null));
        $this->assertNull($this->intRng(null, null)->containedInRange(null));

        $this->assertFalse($this->intRng(1, 4)->containedInRange($this->empty));
        $this->assertTrue($this->empty->containedInRange($this->intRng(-4, 0)));
        $this->assertTrue($this->empty->containedInRange($this->empty));

        $this->assertFalse($this->intRng(8, 14)->containedInRange($this->intRng(10, 20)));
        $this->assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(2, 5)));
        $this->assertTrue($this->intRng(2, 5)->containedInRange($this->intRng(1, 8)));
        $this->assertFalse($this->intRng(null, -6)->containedInRange($this->intRng(-7, -6)));
        $this->assertFalse($this->intRng(null, 4)->containedInRange($this->intRng(0, null)));
        $this->assertFalse($this->intRng(null, null)->containedInRange($this->intRng(5, 6)));
        $this->assertTrue($this->intRng(null, 8)->containedInRange($this->intRng(null, null)));
        $this->assertTrue($this->intRng(null, null)->containedInRange($this->intRng(null, null)));

        $this->assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(9, 12)));
        $this->assertFalse($this->intRng(1, 8)->containedInRange($this->intRng(8, 10)));
        $this->assertFalse($this->intRng(null, 8)->containedInRange($this->intRng(10, null)));
        $this->assertFalse($this->intRng(null, null)->containedInRange($this->empty));
    }

    public function testOverlaps()
    {
        $this->assertNull($this->empty->overlaps(null));
        $this->assertNull($this->intRng(1, 4)->overlaps(null));
        $this->assertNull($this->intRng(null, null)->overlaps(null));

        $this->assertFalse($this->intRng(1, 4)->overlaps($this->empty));
        $this->assertFalse($this->empty->overlaps($this->intRng(-4, 0)));
        $this->assertFalse($this->empty->overlaps($this->empty));

        $this->assertTrue($this->intRng(8, 14)->overlaps($this->intRng(10, 20)));
        $this->assertTrue($this->intRng(1, 8)->overlaps($this->intRng(2, 5)));
        $this->assertTrue($this->intRng(2, 5)->overlaps($this->intRng(1, 8)));
        $this->assertTrue($this->intRng(null, -6)->overlaps($this->intRng(-7, -6)));
        $this->assertTrue($this->intRng(null, 4)->overlaps($this->intRng(0, null)));
        $this->assertTrue($this->intRng(null, null)->overlaps($this->intRng(5, 6)));
        $this->assertTrue($this->intRng(null, 8)->overlaps($this->intRng(null, null)));
        $this->assertTrue($this->intRng(null, null)->overlaps($this->intRng(null, null)));

        $this->assertFalse($this->intRng(1, 8)->overlaps($this->intRng(9, 12)));
        $this->assertFalse($this->intRng(1, 8)->overlaps($this->intRng(8, 10)));
        $this->assertFalse($this->intRng(null, 8)->overlaps($this->intRng(10, null)));
        $this->assertFalse($this->intRng(null, null)->overlaps($this->empty));
    }

    public function testIntersect()
    {
        $this->assertNull($this->empty->intersect(null));
        $this->assertNull($this->intRng(1, 4)->intersect(null));
        $this->assertNull($this->intRng(null, null)->intersect(null));

        $this->assertTrue($this->empty->equals($this->intRng(1, 4)->intersect($this->empty)));
        $this->assertTrue($this->empty->equals($this->empty->intersect($this->intRng(-4, 0))));
        $this->assertTrue($this->empty->equals($this->empty->intersect($this->empty)));

        $this->assertTrue($this->intRng(10, 14)->equals($this->intRng(8, 14)->intersect($this->intRng(10, 20))));
        $this->assertTrue($this->intRng(2, 5)->equals($this->intRng(1, 8)->intersect($this->intRng(2, 5))));
        $this->assertTrue($this->intRng(2, 5)->equals($this->intRng(2, 5)->intersect($this->intRng(1, 8))));
        $this->assertTrue($this->intRng(-7, -6)->equals($this->intRng(null, -6)->intersect($this->intRng(-7, -6))));
        $this->assertTrue($this->intRng(0, 4)->equals($this->intRng(null, 4)->intersect($this->intRng(0, null))));
        $this->assertTrue($this->intRng(5, 6)->equals($this->intRng(null, null)->intersect($this->intRng(5, 6))));
        $this->assertTrue($this->intRng(null, 8)->equals($this->intRng(null, 8)->intersect($this->intRng(null, null))));
        $this->assertTrue($this->intRng(null, null)->equals($this->intRng(null, null)->intersect($this->intRng(null, null))));

        $this->assertTrue($this->empty->equals($this->intRng(1, 8)->intersect($this->intRng(9, 12))));
        $this->assertTrue($this->empty->equals($this->intRng(1, 8)->intersect($this->intRng(8, 10))));
        $this->assertTrue($this->empty->equals($this->intRng(null, 8)->intersect($this->intRng(10, null))));
        $this->assertTrue($this->empty->equals($this->intRng(null, null)->intersect($this->empty)));
    }

    public function testIsFinite()
    {
        $this->assertTrue($this->empty->isFinite());
        $this->assertTrue($this->intRng(1, 2)->isFinite());
        $this->assertTrue($this->intRng(-3, 5)->isFinite());

        $this->assertFalse($this->intRng(0, null)->isFinite());
        $this->assertFalse($this->intRng(null, -4)->isFinite());
        $this->assertFalse($this->intRng(null, null)->isFinite());
    }

    public function testStrictlyLeftOf()
    {
        $this->assertNull($this->empty->strictlyLeftOf(null));
        $this->assertNull($this->intRng(1, 4)->strictlyLeftOf(null));
        $this->assertNull($this->intRng(null, null)->strictlyLeftOf(null));

        $this->assertFalse($this->empty->strictlyLeftOf($this->intRng(1, 4)));
        $this->assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->empty));
        $this->assertFalse($this->empty->strictlyLeftOf($this->empty));

        $this->assertTrue($this->intRng(1, 4)->strictlyLeftOf($this->intRng(5, 9)));
        $this->assertTrue($this->intRng(1, 4)->strictlyLeftOf($this->intRng(4, 9)));
        $this->assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(3, 9)));
        $this->assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 2)));
        $this->assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 1)));
        $this->assertFalse($this->intRng(1, 4)->strictlyLeftOf($this->intRng(-10, 0)));

        $this->assertTrue($this->intRng(null, 4)->strictlyLeftOf($this->intRng(5, 9)));
        $this->assertTrue($this->intRng(null, 4)->strictlyLeftOf($this->intRng(4, 9)));
        $this->assertFalse($this->intRng(null, 4)->strictlyLeftOf($this->intRng(3, 9)));

        $this->assertFalse($this->intRng(null, 4)->strictlyLeftOf($this->intRng(null, 9)));
        $this->assertFalse($this->intRng(null, null)->strictlyLeftOf($this->intRng(3, 9)));
    }

    public function testStrictlyRightOf()
    {
        $this->assertNull($this->empty->strictlyRightOf(null));
        $this->assertNull($this->intRng(1, 4)->strictlyRightOf(null));
        $this->assertNull($this->intRng(null, null)->strictlyRightOf(null));

        $this->assertFalse($this->empty->strictlyRightOf($this->intRng(1, 4)));
        $this->assertFalse($this->intRng(1, 4)->strictlyRightOf($this->empty));
        $this->assertFalse($this->empty->strictlyRightOf($this->empty));

        $this->assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(5, 9)));
        $this->assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(4, 9)));
        $this->assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(3, 9)));
        $this->assertFalse($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 2)));
        $this->assertTrue($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 1)));
        $this->assertTrue($this->intRng(1, 4)->strictlyRightOf($this->intRng(-10, 0)));

        $this->assertTrue($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 3)));
        $this->assertTrue($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 4)));
        $this->assertFalse($this->intRng(4, null)->strictlyRightOf($this->intRng(0, 5)));

        $this->assertFalse($this->intRng(null, 4)->strictlyRightOf($this->intRng(null, 9)));
        $this->assertFalse($this->intRng(null, null)->strictlyRightOf($this->intRng(3, 9)));
    }
}
