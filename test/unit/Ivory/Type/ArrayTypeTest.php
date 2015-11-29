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
        $this->assertSame("'[0:2]={1,4,5}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([1, 4, 5]));
        $this->assertSame("'{}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([]));
        $this->assertSame("'{-42}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([1 => -42]));
        $this->assertSame("'{3,NULL,1}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([1 => 3, null, 1]));

        $this->assertSame(
            "'[0:2]={abc,NULL,def}'::pg_catalog.text[]",
            $this->strArrayType->serializeValue(['abc', null, 'def'])
        );

        try {
            $this->intArrayType->serializeValue(123);
            $this->fail('InvalidArgumentException expected due to non-array input');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializeSpecialStrings()
    {
        $this->assertSame(<<<'STR'
'{abc,NULL,"NULL",NULLs,"","x,y","{}","1\\2","p q","\"r\"",''}'::pg_catalog.text[]
STR
            ,
            $this->strArrayType->serializeValue(
                [1 => 'abc', null, 'NULL', 'NULLs', '', 'x,y', '{}', '1\\2', 'p q', '"r"', "'"]
            )
        );
    }

    public function testSerializeMultiDimensional()
    {
        $this->assertSame(
            "'[0:1][0:2]={{1,4,5},{7,NULL,0}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([[1, 4, 5], [7, null, 0]])
        );

        $this->assertSame(
            "'{{1,4,5},{7,NULL,0}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([1 => [1 => 1, 4, 5], [1 => 7, null, 0]])
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

    public function testSerializeCustomBounds()
    {
        $this->assertSame(
            "'[0:1][-3:-1]={{1,2,3},{4,5,6}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([[-3 => 1, -2 => 2, -1 => 3], [-3 => 4, -2 => 5, -1 => 6]])
        );

        try {
            $this->intArrayType->serializeValue([[1, 2 => 2, 3]]);
            $this->fail('InvalidArgumentException expected due to non-continuous array subscripts');
        }
        catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, -5 => 2, 3]]);
            $this->fail('InvalidArgumentException expected due to non-continuous array subscripts');
        }
        catch (\InvalidArgumentException $e) {
        }
    }

    public function testParseSingleDimensional()
    {
        $this->assertNull($this->intArrayType->parseValue(null));

        $this->assertSame([], $this->intArrayType->parseValue('{}'));
        $this->assertSame([], $this->strArrayType->parseValue('{}'));

        $this->assertSame(
            [1 => 'a', 'b', 'c'],
            $this->strArrayType->parseValue('{a,b,c}')
        );

        $this->assertSame(
            [2 => 'a', 'b', 'c'],
            $this->strArrayType->parseValue('[2:4]={a,b,c}')
        );
    }

    public function testParseSpecialStrings()
    {
        $this->assertSame(
            [1 => 'abc', null, 'NULL', 'NULLs', '', 'x,y', '{}', '1\\2', 'p q', '"r"', "'"],
            $this->strArrayType->parseValue('{abc,NULL,"NULL",NULLs,"","x,y","{}","1\\\\2","p q","\\"r\\"",\'}')
        );
    }

    public function testParseMultiDimensional()
    {
        $this->assertSame(
            [1 => [1 => 'a', '1'], [1 => 'b', '2'], [1 => 'c', null]],
            $this->strArrayType->parseValue('{{a,1},{b,2},{c,NULL}}')
        );

        $this->assertSame(
            [2 => ['a', '1'], ['b', '2'], ['c', null]],
            $this->strArrayType->parseValue('[2:4][0:1]={{a,1},{b,2},{c,NULL}}')
        );
    }
}
