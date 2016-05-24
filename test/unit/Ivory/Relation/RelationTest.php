<?php
namespace Ivory\Relation;

use Ivory\Data\Set\CustomSet;

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

    public function testToSet()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn, 'SELECT id, name, is_active FROM artist ORDER BY name, id');

        $set = $qr->toSet('name');
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertFalse($set->contains('b-side band'));
        $this->assertFalse($set->contains('b-SIDE band'));

        $set = $qr->toSet('name', new CustomSet('strtolower'));
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertTrue($set->contains('b-side band'));
        $this->assertTrue($set->contains('b-SIDE band'));
    }
}
