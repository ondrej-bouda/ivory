<?php
namespace Ivory\Type;

use Ivory\IvoryTestCase;
use Ivory\Type\Std\ConventionalRangeCanonicalFunc;
use Ivory\Type\Std\IntegerType;
use Ivory\Value\Range;

class RangeTypeTest extends IvoryTestCase
{
    /** @var  RangeType */
    private $intRangeType;

    protected function setUp()
    {
        parent::setUp();

        $int = new IntegerType('pg_catalog', 'int4', $this->getIvoryConnection());
        $canonFunc = new ConventionalRangeCanonicalFunc($int);
        $this->intRangeType = new RangeType('pg_catalog', 'int4range', $int, $canonFunc);
    }

    private function intEmpty()
    {
        return Range::createEmpty($this->intRangeType->getSubtype());
    }

    private function intRng($lower, $upper)
    {
        return Range::createFromBounds(
            $this->intRangeType->getSubtype(),
            $this->intRangeType->getCanonicalFunc(),
            $lower,
            $upper
        );
    }

    public function testParseSimple()
    {
        $this->assertNull($this->intRangeType->parseValue(null));
        $this->assertTrue($this->intRangeType->parseValue('[1,4)')->equals($this->intRng(1, 4)));
        $this->assertTrue($this->intRangeType->parseValue('[1,3]')->equals($this->intRng(1, 4)));
        $this->assertTrue($this->intRangeType->parseValue('(0,4)')->equals($this->intRng(1, 4)));
        $this->assertTrue($this->intRangeType->parseValue('(0,3]')->equals($this->intRng(1, 4)));
        $this->assertTrue($this->intRangeType->parseValue('[1,1]')->equals($this->intRng(1, 2)));
        $this->assertTrue($this->intRangeType->parseValue('[1,1)')->isEmpty());
        $this->assertTrue($this->intRangeType->parseValue('[,4)')->equals($this->intRng(null, 4)));
        $this->assertTrue($this->intRangeType->parseValue('[1,)')->equals($this->intRng(1, null)));
        $this->assertTrue($this->intRangeType->parseValue('[,)')->equals($this->intRng(null, null)));
        $this->assertTrue($this->intRangeType->parseValue('[5,4)')->isEmpty());
    }

    public function testSerializeSimple()
    {
        $this->assertSame('NULL', $this->intRangeType->serializeValue(null));

        $this->assertSame("'empty'::pg_catalog.int4range", $this->intRangeType->serializeValue($this->intEmpty()));
        $this->assertSame("'empty'::pg_catalog.int4range", $this->intRangeType->serializeValue([1, 0]));

        $this->assertSame("pg_catalog.int4range(1,4)", $this->intRangeType->serializeValue($this->intRng(1, 4)));
        $this->assertSame("pg_catalog.int4range(1,NULL)", $this->intRangeType->serializeValue($this->intRng(1, null)));
        $this->assertSame("pg_catalog.int4range(NULL,4)", $this->intRangeType->serializeValue($this->intRng(null, 4)));
        $this->assertSame("pg_catalog.int4range(NULL,NULL)", $this->intRangeType->serializeValue($this->intRng(null, null)));
    }
}
