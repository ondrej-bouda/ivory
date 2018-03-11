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
        $this->assertSame(5, $tuple->num);
        $this->assertSame('g', $tuple->letter);
        $this->assertEquals(2.81, $tuple->decim, '', 1e-12);
        $this->assertSame(true, $tuple->flag);
        $this->assertSame('text', $tuple->txt);
    }

    public function testFromRowsExplicitTypes()
    {
        $conn = $this->getIvoryConnection();
        $intRangeType = $conn->getTypeDictionary()->requireTypeByName('int8range', 'pg_catalog');

        $arrRel = ArrayRelation::fromRows(
            [
                ['b' => true, '4.0', '4.0', 4.0, ['a' => 4, 'b' => 'str'], Range::fromBounds(5, 10, '[]')]
            ],
            ['int', 'b' => 'bool', 's', null, 'public.hstore', $intRangeType]
        );
        $tuple = $conn->querySingleTuple(
            '%rel',
            $arrRel
        );
        $this->assertSame(
            [0, 'b', 1, 2, 3, 4],
            array_keys($tuple->toMap()),
            'The order of relation columns does not correspond to the type specification.'
        );
        $this->assertSame(4, $tuple->{0});
        $this->assertSame(true, $tuple->b);
        $this->assertSame('4.0', $tuple->{1});
        $this->assertSame(4.0, $tuple->{2});
        $this->assertSame(['a' => '4', 'b' => 'str'], $tuple->{3});
        $this->assertTrue(Range::fromBounds(5, 11)->equals($tuple->{4}));
    }
}
