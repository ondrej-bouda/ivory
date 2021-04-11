<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;
use PHPUnit\Framework\TestCase;

class ArrayTypeTest extends TestCase
{
    /** @var ArrayType */
    private $intArrayType;
    /** @var ArrayType */
    private $strArrayType;

    protected function setUp(): void
    {
        parent::setUp();

        $intType = new IntegerType('pg_catalog', 'int4');
        $this->intArrayType = new ArrayType($intType, ',');

        $strType = new StringType('pg_catalog', 'text');
        $this->strArrayType = new ArrayType($strType, ',');
    }


    public function testSerializeList(): void
    {
        self::assertSame("'[0:2]={1,4,5}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([1, 4, 5]));
        self::assertSame("'{}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([]));
        self::assertSame("'{}'", $this->intArrayType->serializeValue([], false));
        self::assertSame("'{-42}'::pg_catalog.int4[]", $this->intArrayType->serializeValue([1 => -42]));
        self::assertSame("'{3,NULL,1}'", $this->intArrayType->serializeValue([1 => 3, null, 1], false));

        self::assertSame(
            "'[0:2]={abc,NULL,def}'::pg_catalog.text[]",
            $this->strArrayType->serializeValue(['abc', null, 'def'])
        );

        try {
            $this->intArrayType->serializeValue(123);
            self::fail('InvalidArgumentException expected due to non-array input');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializeSpecialStrings(): void
    {
        self::assertSame(<<<'STR'
'{abc,NULL,"NULL",NULLs,"","x,y","{}","1\\2","p q","\"r\"",''}'::pg_catalog.text[]
STR
            ,
            $this->strArrayType->serializeValue(
                [1 => 'abc', null, 'NULL', 'NULLs', '', 'x,y', '{}', '1\\2', 'p q', '"r"', "'"]
            )
        );
    }

    public function testSerializeMultiDimensional(): void
    {
        self::assertSame(
            "'[0:1][0:2]={{1,4,5},{7,NULL,0}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([[1, 4, 5], [7, null, 0]])
        );

        self::assertSame(
            "'{{1,4,5},{7,NULL,0}}'",
            $this->intArrayType->serializeValue([1 => [1 => 1, 4, 5], [1 => 7, null, 0]], false)
        );

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], null]);
            self::fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], 4]);
            self::fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializeCustomBounds(): void
    {
        self::assertSame(
            "'[0:1][-3:-1]={{1,2,3},{4,5,6}}'::pg_catalog.int4[]",
            $this->intArrayType->serializeValue([[-3 => 1, -2 => 2, -1 => 3], [-3 => 4, -1 => 6, -2 => 5]])
        );

        try {
            $this->intArrayType->serializeValue([[1, 2 => 2, 3]]);
            self::fail('InvalidArgumentException expected due to non-continuous array subscripts');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, -5 => 2, 3]]);
            self::fail('InvalidArgumentException expected due to non-continuous array subscripts');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testSerializePlainMode(): void
    {
        $this->intArrayType->switchToPlainMode();
        $this->strArrayType->switchToPlainMode();

        self::assertSame('ARRAY[1]', $this->intArrayType->serializeValue([1], false));
        self::assertSame('ARRAY[1]::pg_catalog.int4[]', $this->intArrayType->serializeValue([1]));
        self::assertSame('ARRAY[]::pg_catalog.int4[]', $this->intArrayType->serializeValue([]));

        self::assertSame(
            "ARRAY['abc',NULL,'NULL']",
            $this->strArrayType->serializeValue(['abc', null, 'NULL'], false)
        );

        self::assertSame(
            'ARRAY[[1,2,NULL],[4,6,5]]',
            $this->intArrayType->serializeValue([[-3 => 1, -2 => 2, -1 => null], [-3 => 4, -1 => 6, -2 => 5]], false)
        );
        self::assertSame(
            'ARRAY[[1,2,3]]::pg_catalog.int4[]',
            $this->intArrayType->serializeValue([[1, 2 => 2, 3]])
        );


        try {
            $this->intArrayType->serializeValue(123);
            self::fail('InvalidArgumentException expected due to non-array input');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], null]);
            self::fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $this->intArrayType->serializeValue([[1, 2, 3], 4]);
            self::fail('InvalidArgumentException expected due to non-matching array dimensions');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testParseSingleDimensional(): void
    {
        self::assertSame([], $this->intArrayType->parseValue('{}'));
        self::assertSame([], $this->strArrayType->parseValue('{}'));

        self::assertSame(
            [1 => 'a', 'b', 'c'],
            $this->strArrayType->parseValue('{a,b,c}')
        );

        self::assertSame(
            [2 => 'a', 'b', 'c'],
            $this->strArrayType->parseValue('[2:4]={a,b,c}')
        );
    }

    public function testParseSpecialStrings(): void
    {
        self::assertSame(
            [1 => 'abc', null, 'NULL', 'NULLs', '', 'x,y', '{}', '1\\2', 'ab', 'p q', '"r"', "'", '  , \\ " \''],
            $this->strArrayType->parseValue(<<<'STR'
{abc,NULL,"NULL",NULLs,"","x,y","{}","1\\2","a\b","p q",  "\"r\""  ,', \  \, \\ \" ' }
STR
            )
        );

        self::assertSame(
            [3 => [4 => ' ,\\ a ; a b']],
            $this->strArrayType->parseValue('  [3:3] [4:4] =  { { \\ \\,\\\\ \\a ; a b } } ')
        );
    }

    public function testParseMultiDimensional(): void
    {
        self::assertSame(
            [1 => [1 => 'a', '1'], [1 => 'b', '2'], [1 => 'c', null]],
            $this->strArrayType->parseValue('{{a,1},{b,2},{c,NULL}}')
        );

        self::assertSame(
            [2 => ['a', '1'], ['b', '2'], ['c', null]],
            $this->strArrayType->parseValue('[2:4][0:1]={{a,1},{b,2},{c,NULL}}')
        );

        self::assertSame(
            [3 => [4 => 'a b']],
            $this->strArrayType->parseValue('[3:3][4:4]={{a b}}')
        );
    }

    public function testParseWhitespace(): void
    {
        // "You can add whitespace before a left brace or after a right brace."
        self::assertSame(
            [1 => [1 => 'a']],
            $this->strArrayType->parseValue("{\t\n {a}  }")
        );
        self::assertSame(
            [],
            $this->strArrayType->parseValue('  {} ')
        );

        // "You can also add whitespace before or after any individual item string."
        self::assertSame(
            [1 => 'a  b', 'c'],
            $this->strArrayType->parseValue('{  a  b   ,c}')
        );

        // Although not mentioned in the PostgreSQL manual, whitespace is also allowed (and ignored) in the array bounds
        // decoration. It seems the only forbidden place for whitespace is a single bounds specification bracket.
        self::assertSame(
            [3 => [4 => 'a b']],
            $this->strArrayType->parseValue('  [3:3] [4:4] =  { { a b } } ')
        );
    }
}
