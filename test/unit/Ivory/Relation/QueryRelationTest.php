<?php
namespace Ivory\Relation;

class QueryRelationTest extends \Ivory\IvoryTestCase
{
    public function testDummy()
    {
        $this->assertEquals(3, $this->getConnection()->getRowCount('artist'));
    }
}
