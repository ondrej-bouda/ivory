<?php
declare(strict_types=1);

namespace Ivory\Relation;

use Ivory\Exception\AmbiguousException;

class AmbiguousRelationTest extends \Ivory\IvoryTestCase
{
    /** @var IRelation */
    private $rel;

    protected function setUp()
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();
        $this->rel = $conn->query(
            'SELECT 7 AS a, 8 AS a, 9 AS b'
        );
    }

    public function testCol()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->col('a');
    }

    public function testProject()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->project(['a']);
    }

    public function testAssocFrom()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->assoc('a', 'b');
    }

    public function testAssocTo()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->assoc('b', 'a');
    }

    public function testMap()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->map('a');
    }

    public function testMultimap()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->multimap('a');
    }

    public function testToSet()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->toSet('a');
    }

    public function testValue()
    {
        $this->expectException(AmbiguousException::class);
        $this->rel->value('a');
    }
}
