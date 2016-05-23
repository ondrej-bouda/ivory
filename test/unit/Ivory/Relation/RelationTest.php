<?php
namespace Ivory\Relation;

class RelationTest extends \Ivory\IvoryTestCase
{
    public function testChaining()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT 1 AS a, 2 AS b, 3 AS c, 4 AS d"
        );
        $this->assertSame(
            [['n' => 2, 'z' => -3]],
            $qr->project(['x' => 'a', 'y' => 'b', 'z' => 'c'])
                ->rename(['x' => 'm', 'y' => 'n'])
                ->project(['n', 'z' => function (ITuple $tuple) {
                    $cols = $tuple->getColumns();
                    $colNames = array_map(function (IColumn $col) { return $col->getName(); }, $cols);
                    $this->assertSame(['m', 'n', 'z'], $colNames);
                    return -$tuple['z'];
                }])
                ->toArray()
        );
    }
}
