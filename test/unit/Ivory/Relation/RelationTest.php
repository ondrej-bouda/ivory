<?php
namespace Ivory\Relation;

use Ivory\Data\Set\CustomSet;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Value\Point;

class RelationTest extends \Ivory\IvoryTestCase
{
    public function testBasic()
    {
        $conn = $this->getIvoryConnection();
        $qr = $conn->query(
            'SELECT *
             FROM (VALUES (point(0, 0), point(3, 4)), (point(1.3, 4), point(8, -2))) v ("A", "B")'
        );
        $this->assertSame(2, count($qr));
        $this->assertEquals(Point::fromCoords(0, 0), $qr->tuple(0)['A']);
        $this->assertEquals(Point::fromCoords(0, 0), $qr->value('A', 0));
        $this->assertEquals(
            [
                ['A' => Point::fromCoords(0, 0), 'B' => Point::fromCoords(3, 4)],
                ['A' => Point::fromCoords(1.3, 4), 'B' => Point::fromCoords(8, -2)],
            ],
            $qr->toArray()
        );
    }

    public function testGetColumns()
    {
        $conn = $this->getIvoryConnection();
        $qr = $conn->query(
            "SELECT 1 AS a, 'foo' AS b, 3 AS c, 4 AS d"
        );
        $cols = $qr->getColumns();
        $this->assertEquals(4, count($cols));
        $this->assertSame(['a', 'b', 'c', 'd'], array_map(function (IColumn $c) { return $c->getName(); }, $cols));
        $this->assertSame([[1], ['foo'], [3], [4]], array_map(function (IColumn $c) { return $c->toArray(); }, $cols));
    }

    public function testExtend()
    {
        $conn = $this->getIvoryConnection();
        $qr = $conn->query(
            'SELECT *
             FROM (VALUES (point(0, 0), point(3, 4)), (point(1.3, 4), point(8, -2))) v ("A", "B")'
        );

        $ext = $qr->extend([
            'dist' => function (ITuple $tuple) {
                /** @var Point $a */
                $a = $tuple['A'];
                /** @var Point $b */
                $b = $tuple['B'];
                $x = $a->getX() - $b->getX();
                $y = $a->getY() - $b->getY();
                return sqrt($x * $x + $y * $y);
            },
        ]);

        $ext2 = $ext->extend([
            'upleft' => function (ITuple $t) {
                return Point::fromCoords(min($t[0]->getX(), $t[1]->getX()), max($t[0]->getY(), $t[1]->getY()));
            },
        ]);

        $this->assertSame(2, count($ext));
        $this->assertSame(2, count($ext2));

        $this->assertSame(
            ['A', 'B', 'dist'],
            array_map(function (IColumn $c) { return $c->getName(); }, $ext->getColumns())
        );
        $this->assertSame(
            ['A', 'B', 'dist', 'upleft'],
            array_map(function (IColumn $c) { return $c->getName(); }, $ext2->getColumns())
        );

        $this->assertEquals(5, $ext->tuple(0)['dist'], '', 1e-9);
        $this->assertEquals(8.993886812719, $ext2->tuple(1)['dist'], '', 1e-9);

        $this->assertEquals(Point::fromCoords(0, 4), $ext2->tuple(0)['upleft']);
        $this->assertEquals(Point::fromCoords(1.3, 4), $ext2->tuple(1)['upleft']);

        try {
            $c = $ext->col('upleft');
            $this->fail("Column {$c->getName()} should not have been defined");
        }
        catch (UndefinedColumnException $e) {
        }
    }

    public function testChaining()
    {
        $conn = $this->getIvoryConnection();
        $qr = $conn->query(
            'SELECT 1 AS a, 2 AS b, 3 AS c, 4 AS d'
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
        $qr = $conn->query(
            'SELECT id, name, is_active FROM artist ORDER BY name, id'
        );

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
