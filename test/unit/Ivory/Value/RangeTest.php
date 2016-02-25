<?php
namespace Ivory\Value;

use Ivory\IvoryTestCase;
use Ivory\Type\IRangeCanonicalFunc;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\Std\ConventionalRangeCanonicalFunc;
use Ivory\Type\Std\IntegerType;

class RangeTest extends IvoryTestCase
{
    /** @var ITotallyOrderedType */
    private $intType;
    /** @var IRangeCanonicalFunc */
    private $intCanonFunc;
    /** @var Range */
    private $empty;
    /** @var Range */
    private $finite;
    /** @var Range */
    private $infinite;

    protected function setUp()
    {
        $this->intType = new IntegerType('pg_catalog', 'int4', $this->getIvoryConnection());
        $this->intCanonFunc = new ConventionalRangeCanonicalFunc($this->intType);

        $this->empty = Range::createEmpty($this->intType);
        $this->finite = Range::createFromBounds($this->intType, $this->intCanonFunc, 1, 4);
        $this->infinite = Range::createFromBounds($this->intType, $this->intCanonFunc, null, 4);
    }

    private function intRng($lower, $upper)
    {
        return Range::createFromBounds(
            $this->intType,
            $this->intCanonFunc,
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
            Range::createFromBounds($this->intType, $this->intCanonFunc, 1, 4)
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, $this->intCanonFunc, 1, 3, '[]')
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, $this->intCanonFunc, 0, 3, '(]')
                ->equals($this->intRng(1, 4))
        );

        $this->assertTrue(
            Range::createFromBounds($this->intType, $this->intCanonFunc, 0, 4, '()')
                ->equals($this->intRng(1, 4))
        );

        $this->assertFalse(
            Range::createFromBounds($this->intType, null, 1, 3, '[]')
                ->equals($this->intRng(1, 4))
        );

        $this->assertSame('[]', Range::createFromBounds($this->intType, null, 1, 3, '[]')->getBoundsSpec());
        $this->assertSame('(]', Range::createFromBounds($this->intType, null, null, 3, '[]')->getBoundsSpec());
        $this->assertSame('[)', Range::createFromBounds($this->intType, null, 1, null, '[)')->getBoundsSpec());
        $this->assertSame('()', Range::createFromBounds($this->intType, null, null, null, '[]')->getBoundsSpec());

        $this->assertTrue(Range::createFromBounds($this->intType, $this->intCanonFunc, 1, 1)->isEmpty());
        $this->assertTrue(Range::createFromBounds($this->intType, null, 1, 1)->isEmpty());

        $this->assertTrue(Range::createFromBounds($this->intType, $this->intCanonFunc, 1, 2, '()')->isEmpty());
        $this->assertFalse(Range::createFromBounds($this->intType, null, 1, 2, '()')->isEmpty());
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
}
