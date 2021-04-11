<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Value\Range;
use PHPUnit\Framework\TestCase;

class RangeTypeTest extends TestCase
{
    /** @var RangeType */
    private $intRangeType;

    protected function setUp(): void
    {
        parent::setUp();

        $int = new IntegerType('pg_catalog', 'int4');
        $this->intRangeType = new RangeType('pg_catalog', 'int4range', $int);
    }

    public function testParseValue(): void
    {
        $rng1 = $this->intRangeType->parseValue('[1,4)');
        self::assertSame(1, $rng1->getLower());
        self::assertSame(4, $rng1->getUpper());
        self::assertSame('[)', $rng1->getBoundsSpec());

        $rng2 = $this->intRangeType->parseValue('[1,3]');
        self::assertSame('[]', $rng2->getBoundsSpec());

        $rng3 = $this->intRangeType->parseValue('(0,4)');
        self::assertSame('()', $rng3->getBoundsSpec());

        $rng4 = $this->intRangeType->parseValue('[1,1)');
        self::assertTrue($rng4->isEmpty());

        $rng5 = $this->intRangeType->parseValue('[,4)');
        self::assertSame(null, $rng5->getLower());
        self::assertSame(4, $rng5->getUpper());
        self::assertSame('()', $rng5->getBoundsSpec());

        $rng6 = $this->intRangeType->parseValue('[,)');
        self::assertSame(null, $rng6->getLower());
        self::assertSame(null, $rng6->getUpper());
        self::assertSame('()', $rng6->getBoundsSpec());

        $rng7 = $this->intRangeType->parseValue('[5,4)');
        self::assertTrue($rng7->isEmpty());
    }

    public function testSerializeValue(): void
    {
        self::assertSame(
            'NULL',
            $this->intRangeType->serializeValue(null, false)
        );

        self::assertSame(
            "'empty'",
            $this->intRangeType->serializeValue(Range::empty(), false)
        );
        self::assertSame(
            "pg_catalog.int4range 'empty'",
            $this->intRangeType->serializeValue(Range::empty())
        );
        self::assertSame(
            "'empty'",
            $this->intRangeType->serializeValue([1, 0], false)
        );

        self::assertSame(
            "pg_catalog.int4range(1,4)",
            $this->intRangeType->serializeValue(Range::fromBounds(1, 4))
        );
        self::assertSame(
            "pg_catalog.int4range(1,NULL)",
            $this->intRangeType->serializeValue(Range::fromBounds(1, null), false)
        );
        self::assertSame(
            "pg_catalog.int4range(NULL,4)",
            $this->intRangeType->serializeValue(Range::fromBounds(null, 4))
        );
        self::assertSame(
            "pg_catalog.int4range(NULL,NULL)",
            $this->intRangeType->serializeValue(Range::fromBounds(null, null))
        );
    }
}
