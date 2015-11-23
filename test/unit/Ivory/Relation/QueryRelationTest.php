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
        $this->markTestSkipped();
        return;

        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn, 'SELECT id, name, is_active FROM artist ORDER BY name, id');
        /** @var ITuple[] $tuples */
        $tuples = [];
        foreach ($qr as $tuple) {
            $tuples[] = $tuple;
        }
        $expArray = [
            ['id' => 2, 'name' => 'Metallica', 'is_active' => false],
            ['id' => 1, 'name' => 'Tommy Emanuel', 'is_active' => null],
            ['id' => 3, 'name' => 'The Piano Guys', 'is_active' => true],
        ];
        $this->assertEquals(count($expArray), count($qr));
        $this->assertEquals(count($expArray), count($tuples));
        $this->assertSame($expArray[0], $tuples[0]->toArray());
        $this->assertSame($expArray[1], $tuples[1]->toArray());
        $this->assertSame($expArray[2], $tuples[2]->toArray());

        $this->assertSame($expArray, $qr->toArray());
    }
}
