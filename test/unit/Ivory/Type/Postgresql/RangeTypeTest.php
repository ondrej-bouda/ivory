<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Value\Range;

class RangeTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var  RangeType */
    private $intRangeType;

    protected function setUp()
    {
        parent::setUp();

        $int = new IntegerType('pg_catalog', 'int4');
        $this->intRangeType = new RangeType('pg_catalog', 'int4range', $int);
    }

    private function intEmpty()
    {
        return Range::createEmpty($this->intRangeType->getSubtype());
    }

    private function intRng($lower, $upper)
    {
        return Range::createFromBounds(
            $this->intRangeType->getSubtype(),
            $lower,
            $upper
        );
    }

    public function testParseValue()
    {
        $rng1 = $this->intRangeType->parseValue('[1,4)');
        $this->assertSame(1, $rng1->getLower());
        $this->assertSame(4, $rng1->getUpper());
        $this->assertSame('[)', $rng1->getBoundsSpec());

        $rng2 = $this->intRangeType->parseValue('[1,3]');
        $this->assertSame('[]', $rng2->getBoundsSpec());

        $rng3 = $this->intRangeType->parseValue('(0,4)');
        $this->assertSame('()', $rng3->getBoundsSpec());

        $rng4 = $this->intRangeType->parseValue('[1,1)');
        $this->assertTrue($rng4->isEmpty());

        $rng5 = $this->intRangeType->parseValue('[,4)');
        $this->assertSame(null, $rng5->getLower());
        $this->assertSame(4, $rng5->getUpper());
        $this->assertSame('()', $rng5->getBoundsSpec());

        $rng6 = $this->intRangeType->parseValue('[,)');
        $this->assertSame(null, $rng6->getLower());
        $this->assertSame(null, $rng6->getUpper());
        $this->assertSame('()', $rng6->getBoundsSpec());

        $rng7 = $this->intRangeType->parseValue('[5,4)');
        $this->assertTrue($rng7->isEmpty());
    }

    public function testSerializeValue()
    {
        $this->assertSame('NULL', $this->intRangeType->serializeValue(null));

        $this->assertSame("'empty'::pg_catalog.int4range", $this->intRangeType->serializeValue($this->intEmpty()));
        $this->assertSame("'empty'::pg_catalog.int4range", $this->intRangeType->serializeValue([1, 0]));

        $this->assertSame("pg_catalog.int4range(1,4)", $this->intRangeType->serializeValue($this->intRng(1, 4)));
        $this->assertSame("pg_catalog.int4range(1,NULL)", $this->intRangeType->serializeValue($this->intRng(1, null)));
        $this->assertSame("pg_catalog.int4range(NULL,4)", $this->intRangeType->serializeValue($this->intRng(null, 4)));
        $this->assertSame("pg_catalog.int4range(NULL,NULL)",
            $this->intRangeType->serializeValue($this->intRng(null, null))
        );
    }
}
