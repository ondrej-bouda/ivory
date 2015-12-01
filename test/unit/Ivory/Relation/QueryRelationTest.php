<?php
namespace Ivory\Relation;

class QueryRelationTest extends \Ivory\IvoryTestCase
{
    public function testDummy()
    {
        $this->assertEquals(3, $this->getConnection()->getRowCount('artist'));
    }

    public function testBasic()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn, 'SELECT id, name, is_active FROM artist ORDER BY name, id');
        /** @var ITuple[] $tuples */
        $tuples = [];
        foreach ($qr as $tuple) {
            $tuples[] = $tuple;
        }
        $expArray = [
            ['id' => 2, 'name' => 'Metallica', 'is_active' => false],
            ['id' => 1, 'name' => 'The Piano Guys', 'is_active' => true],
            ['id' => 3, 'name' => 'Tommy Emmanuel', 'is_active' => null],
        ];
        $this->assertEquals(count($expArray), count($qr));
        $this->assertEquals(count($expArray), count($tuples));
        $this->assertSame($expArray[0], $tuples[0]->toMap());
        $this->assertSame($expArray[1], $tuples[1]->toMap());
        $this->assertSame($expArray[2], $tuples[2]->toMap());

        $this->assertSame($expArray, $qr->toArray());
    }
}
