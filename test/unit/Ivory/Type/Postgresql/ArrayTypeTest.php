<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;

class ArrayTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var ArrayType */
    private $intArrayType;
    /** @var ArrayType */
    private $strArrayType;

    protected function setUp()
    {
        parent::setUp();

        $intType = new IntegerType('pg_catalog', 'int4');
        $this->intArrayType = new ArrayType($intType, ',');

        $strType = new StringType('pg_catalog', 'text');
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
        } catch (\InvalidArgumentException $e) {
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
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], 4]);
            $this->fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializeCustomBounds()
    {
        $this->assertSame(
            "'[0:1][-3:-1]={{1,2,3},{4,5,6}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([[-3 => 1, -2 => 2, -1 => 3], [-3 => 4, -1 => 6, -2 => 5]])
        );

        try {
            $this->intArrayType->serializeValue([[1, 2 => 2, 3]]);
            $this->fail('InvalidArgumentException expected due to non-continuous array subscripts');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, -5 => 2, 3]]);
            $this->fail('InvalidArgumentException expected due to non-continuous array subscripts');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializePlainMode()
    {
        $this->intArrayType->switchToPlainMode();
        $this->strArrayType->switchToPlainMode();

        $this->assertSame('ARRAY[1]::pg_catalog.int4[]', $this->intArrayType->serializeValue([1]));
        $this->assertSame('ARRAY[]::pg_catalog.int4[]', $this->intArrayType->serializeValue([]));

        $this->assertSame(
            "ARRAY['abc',NULL,'NULL']::pg_catalog.text[]",
            $this->strArrayType->serializeValue(['abc', null, 'NULL'])
        );

        $this->assertSame(
            'ARRAY[[1,2,NULL],[4,6,5]]::pg_catalog.int4[]',
            $this->intArrayType->serializeValue([[-3 => 1, -2 => 2, -1 => null], [-3 => 4, -1 => 6, -2 => 5]])
        );
        $this->assertSame(
            'ARRAY[[1,2,3]]::pg_catalog.int4[]',
            $this->intArrayType->serializeValue([[1, 2 => 2, 3]])
        );


        try {
            $this->intArrayType->serializeValue(123);
            $this->fail('InvalidArgumentException expected due to non-array input');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], null]);
            $this->fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], 4]);
            $this->fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testParseSingleDimensional()
    {
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
            [1 => 'abc', null, 'NULL', 'NULLs', '', 'x,y', '{}', '1\\2', 'ab', 'p q', '"r"', "'", '  , \\ " \''],
            $this->strArrayType->parseValue(<<<'STR'
{abc,NULL,"NULL",NULLs,"","x,y","{}","1\\2","a\b","p q",  "\"r\""  ,', \  \, \\ \" ' }
STR
            )
        );

        $this->assertSame(
            [3 => [4 => ' ,\\ a ; a b']],
            $this->strArrayType->parseValue('  [3:3] [4:4] =  { { \\ \\,\\\\ \\a ; a b } } ')
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

        $this->assertSame(
            [3 => [4 => 'a b']],
            $this->strArrayType->parseValue('[3:3][4:4]={{a b}}')
        );
    }

    public function testParseWhitespace()
    {
        // "You can add whitespace before a left brace or after a right brace."
        $this->assertSame(
            [1 => [1 => 'a']],
            $this->strArrayType->parseValue("{\t\n {a}  }")
        );
        $this->assertSame(
            [],
            $this->strArrayType->parseValue('  {} ')
        );

        // "You can also add whitespace before or after any individual item string."
        $this->assertSame(
            [1 => 'a  b', 'c'],
            $this->strArrayType->parseValue('{  a  b   ,c}')
        );

        // Although not mentioned in the PostgreSQL manual, whitespace is also allowed (and ignored) in the array bounds
        // decoration. It seems the only forbidden place for whitespace is a single bounds specification bracket.
        $this->assertSame(
            [3 => [4 => 'a b']],
            $this->strArrayType->parseValue('  [3:3] [4:4] =  { { a b } } ')
        );
    }

    public function testCompareValues()
    {
        $this->assertNull($this->intArrayType->compareValues(null, [1, 2, 3]));
        $this->assertNull($this->intArrayType->compareValues([1, 2, 3], null));
        $this->assertNull($this->intArrayType->compareValues(null, null));
        $this->assertNull($this->intArrayType->compareValues(null, []));
        $this->assertNull($this->intArrayType->compareValues(null, [[1], [2], [3]]));

        $this->assertTrue($this->intArrayType->compareValues([], [1]) < 0);
        $this->assertTrue($this->intArrayType->compareValues([1], []) > 0);

        $this->assertSame(0, $this->intArrayType->compareValues([1, 3], [1, 3]));
        $this->assertTrue($this->intArrayType->compareValues([1, 2, 3], [1, 3]) < 0);
        $this->assertTrue($this->intArrayType->compareValues([1, 3], [1, 2, 3]) > 0);
        $this->assertTrue($this->intArrayType->compareValues([1, 3, 3], [1, 3]) > 0);
        $this->assertTrue($this->intArrayType->compareValues([1, 3], [1, 3, 3]) < 0);

        $this->assertSame(0, $this->intArrayType->compareValues([[1], [3]], [[1], [3]]));
        $this->assertTrue($this->intArrayType->compareValues([[1], [2], [3]], [[1], [3], [2]]) < 0);
        $this->assertTrue($this->intArrayType->compareValues([[2], [1], [3]], [[1], [3], [2]]) > 0);
        $this->assertTrue($this->intArrayType->compareValues([[1], [3], [3]], [[1], [3], [2]]) > 0);
        $this->assertTrue($this->intArrayType->compareValues([[1], [3], [2]], [[1], [3], [3]]) < 0);

        $this->assertTrue($this->intArrayType->compareValues([1 => [1], [3]], [[1], [3]]) > 0);
    }
}
