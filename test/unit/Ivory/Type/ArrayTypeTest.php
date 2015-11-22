<?php
namespace Ivory\Type;

use Ivory\IvoryTestCase;
use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;

class ArrayTypeTest extends IvoryTestCase
{
    /** @var IType */
    private $intArrayType;
    /** @var IType */
    private $strArrayType;

    protected function setUp()
    {
        $intType = new IntegerType('pg_catalog', 'int4', $this->getIvoryConnection());
        $this->intArrayType = new ArrayType($intType, ',');

        $strType = new StringType('pg_catalog', 'text', $this->getIvoryConnection());
        $this->strArrayType = new ArrayType($strType, ',');
    }


    public function testSerializeList()
    {
        $this->assertSame('ARRAY[1,4,5]::pg_catalog.int4[]', $this->intArrayType->serializeValue([1, 4, 5]));
        $this->assertSame('ARRAY[]::pg_catalog.int4[]', $this->intArrayType->serializeValue([]));
        $this->assertSame('ARRAY[-42]::pg_catalog.int4[]', $this->intArrayType->serializeValue([-42]));
        $this->assertSame('ARRAY[3,NULL,1]::pg_catalog.int4[]', $this->intArrayType->serializeValue([3, null, 1]));

        $this->assertSame(
            "ARRAY['abc',NULL,'NULL']::pg_catalog.text[]",
            $this->strArrayType->serializeValue(['abc', null, 'NULL'])
        );
    }

    public function testSerializeMultiDimensional()
    {
//        $this->assertSame('ARRAY[]::pg_catalog.int4[][][]') // FIXME: what to give to the serializeValue()? but is it relevant? does Postgres care about dimensions of an empty array?
        $this->assertSame(
            'ARRAY[ARRAY[1,4,5],ARRAY[7,NULL,0]]::pg_catalog.int4[][]',
            $this->intArrayType->serializeValue([[1, 4, 5], [7, null, 0]])
        );

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], null]);
            $this->fail('InvalidArgumentException expected due to non-matching array dimensions');
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], 4]);
            $this->fail('InvalidArgumentException expected due to non-matching array dimensions');
        }
        catch (\InvalidArgumentException $e) {
        }
    }
}
