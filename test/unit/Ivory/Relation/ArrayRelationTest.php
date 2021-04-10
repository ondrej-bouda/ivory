<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\IvoryTestCase;
use Ivory\Value\Range;

class ArrayRelationTest extends IvoryTestCase
{
    public function testFromRowsAutoTyped()
    {
        $arrRel = ArrayRelation::fromRows(
            [
                [1, 'a', 3.14, false, null],
                [5, 'g', 2.81, true, 'text'],
            ]
        );

        $conn = $this->getIvoryConnection();
        $tuple = $conn->querySingleTuple(
            "SELECT * FROM (%rel) AS vals (num, letter, decim, flag, txt) WHERE flag",
            $arrRel
        );
        self::assertSame(5, $tuple->num);
        self::assertSame('g', $tuple->letter);
        self::assertEqualsWithDelta(2.81, $tuple->decim, 1e-12);
        self::assertSame(true, $tuple->flag);
        self::assertSame('text', $tuple->txt);
    }

    public function testFromRowsExplicitTypes()
    {
        $conn = $this->getIvoryConnection();
        $intRangeType = $conn->getTypeDictionary()->requireTypeByName('int8range', 'pg_catalog');

        $arrRel = ArrayRelation::fromRows(
            [
                [null, null, null, null, null, null],
                ['b' => true, '4.0', '4.0', 4.0, ['a' => 4, 'b' => 'str'], Range::fromBounds(5, 10, '[]'), null]
            ],
            ['int', 'b' => 'bool', 's', null, 'public.hstore', $intRangeType, null]
        );
        $rel = $conn->query(
            '%rel',
            $arrRel
        );
        $tuple0 = $rel->tuple(0);
        $tuple1 = $rel->tuple(1);

        self::assertSame(
            [0, 'b', 1, 2, 3, 4, 5],
            array_keys($tuple1->toMap()),
            'The order of relation columns does not correspond to the type specification.'
        );

        self::assertNull($tuple0->{0});
        self::assertNull($tuple0->b);
        self::assertNull($tuple0->{1});
        self::assertNull($tuple0->{2});
        self::assertNull($tuple0->{3});
        self::assertNull($tuple0->{4});
        self::assertNull($tuple0->{5});

        self::assertSame(4, $tuple1->{0});
        self::assertSame(true, $tuple1->b);
        self::assertSame('4.0', $tuple1->{1});
        self::assertSame(4.0, $tuple1->{2});
        self::assertSame(['a' => '4', 'b' => 'str'], $tuple1->{3});
        self::assertTrue(Range::fromBounds(5, 11)->equals($tuple1->{4}));
        self::assertNull($tuple1->{5});
    }

    public function testFromRowsWithValueSerializers()
    {
        $arrRel = ArrayRelation::fromRows(
            [
                ['val' => 'a', 'cond' => 'TRUE'],
                ['val' => 'b', 'cond' => '1 = 3'],
            ],
            ['val' => 'text', 'cond' => 'sql']
        );

        $conn = $this->getIvoryConnection();
        $rel = $conn->query(
            '%rel WHERE cond',
            $arrRel
        );
        self::assertSame(1, $rel->count());
        self::assertSame('a', $rel->value('val'));
    }

    public function testSerialize()
    {
        $orig = ArrayRelation::fromRows(
            [
                ['a' => 5, 'b' => true, 'c' => 6],
                ['a' => 7, 'b' => false, 'c' => -1]
            ],
            ['a' => 'int', 'b' => null, 'c' => 's']
        );
        $serialized = serialize($orig);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(ArrayRelation::class, $unserialized);
        assert($unserialized instanceof ArrayRelation);

        self::assertSame(['a' => 5, 'b' => true, 'c' => 6], $unserialized->tuple(0)->toMap());
    }
}
