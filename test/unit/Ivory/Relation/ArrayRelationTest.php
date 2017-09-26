<?php
declare(strict_types=1);

namespace Ivory\Relation;

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
        $this->assertSame(5, $tuple->num);
        $this->assertSame('g', $tuple->letter);
        $this->assertEquals(2.81, $tuple->decim, '', 1e-12);
        $this->assertSame(true, $tuple->flag);
        $this->assertSame('text', $tuple->txt);
    }
}
