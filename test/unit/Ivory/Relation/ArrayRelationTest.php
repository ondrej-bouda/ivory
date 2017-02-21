<?php
namespace Ivory\Relation;

use Ivory\Value\Decimal;

class ArrayRelationTest extends \Ivory\IvoryTestCase
{
    public function testAutodetect()
    {
        $conn = $this->getIvoryConnection();
        $arrRel = ArrayRelation::createAutodetect(
            [
                [1, 'a', 3.14, false, null],
                [5, 'g', 2.81, true, 'text'],
            ],
            $conn->getTypeDictionary()
        );
        $tuple = $conn->querySingleTuple(
            "SELECT * FROM (%rel) AS vals (num, letter, decim, flag, txt) WHERE flag",
            $arrRel
        );
        $this->assertSame(5, $tuple['num']);
        $this->assertSame('g', $tuple['letter']);
        $this->assertEquals(Decimal::fromNumber('2.81'), $tuple['decim']);
        $this->assertSame(true, $tuple['flag']);
        $this->assertSame('text', $tuple['txt']);
    }
}
