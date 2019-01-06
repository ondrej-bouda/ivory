<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\IConnection;
use Ivory\Data\Set\CustomSet;
use Ivory\Exception\AmbiguousException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\IvoryTestCase;
use Ivory\Value\Point;

class RelationTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }

    public function testBasic()
    {
        $rel = $this->conn->query(
            'SELECT id, name, is_active FROM artist WHERE id IN (1,2,3,4) ORDER BY name, id'
        );
        $tuples = [];
        foreach ($rel as $tuple) {
            $this->assertInstanceOf(ITuple::class, $tuple);
            $tuples[] = $tuple;
        }
        $this->assertSame(
            [
                ['id' => 2, 'name' => 'Metallica', 'is_active' => false],
                ['id' => 4, 'name' => 'Robbie Williams', 'is_active' => null],
                ['id' => 1, 'name' => 'The Piano Guys', 'is_active' => true],
                ['id' => 3, 'name' => 'Tommy Emmanuel', 'is_active' => null],
            ],
            $rel->toArray()
        );
    }

    public function testResultAccess()
    {
        $rel = $this->conn->query(
            'SELECT artist.name AS artist_name, array_agg(album.name ORDER BY album.year) AS album_names
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             WHERE artist.id IN (1,2,3)
             GROUP BY artist.id
             ORDER BY artist.name'
        );

        $tuples = iterator_to_array($rel);
        assert($tuples[0] instanceof ITuple);
        assert($tuples[1] instanceof ITuple);
        assert($tuples[2] instanceof ITuple);
        $expArray = [
            ['artist_name' => 'Metallica', 'album_names' => [1 => 'Black Album', 'S & M']],
            ['artist_name' => 'The Piano Guys', 'album_names' => [1 => 'The Piano Guys']],
            ['artist_name' => 'Tommy Emmanuel', 'album_names' => [1 => 'Live One']],
        ];

        $this->assertSame(count($expArray), count($rel));
        $this->assertSame(count($expArray), count($tuples));
        $this->assertSame(count($expArray), count($tuples));

        $this->assertSame($expArray[0], $tuples[0]->toMap());
        $this->assertSame($expArray[1], $tuples[1]->toMap());
        $this->assertSame($expArray[2], $tuples[2]->toMap());

        $this->assertSame($expArray[0], $rel->tuple(0)->toMap());
        $this->assertSame($expArray[0], $rel->tuple(-3)->toMap());
        $this->assertSame($expArray[1], $rel->tuple(1)->toMap());
        $this->assertSame($expArray[1], $rel->tuple(-2)->toMap());
        $this->assertSame($expArray[2], $rel->tuple(2)->toMap());
        $this->assertSame($expArray[2], $rel->tuple(-1)->toMap());
        try {
            $rel->tuple(3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }
        try {
            $rel->tuple(-4);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $this->assertSame($expArray, $rel->toArray());

        $this->assertSame('S & M', $rel->value(1)[2]);
        $this->assertSame('S & M', $rel->value('album_names')[2]);
        $this->assertSame('The Piano Guys', $rel->value(0, 1));
        $this->assertSame('The Piano Guys', $rel->value('artist_name', 1));

        $evaluator = function (ITuple $tuple) {
            return sprintf('%s (%d)', $tuple->artist_name[0], count($tuple->album_names));
        };
        $this->assertSame('T (1)', $rel->value($evaluator, 1));

        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $rel->col(0)->toArray()
        );
        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $rel->col('artist_name')->toArray()
        );
        $this->assertSame(
            ['M (2)', 'T (1)', 'T (1)'],
            $rel->col($evaluator)->toArray()
        );

        $this->assertSame('Metallica', $rel->col(0)->value(0));
        $this->assertSame('The Piano Guys', $rel->col(0)->value(1));
        $this->assertSame('Tommy Emmanuel', $rel->col(0)->value(2));
        $this->assertSame('Tommy Emmanuel', $rel->col(0)->value(-1));
        $this->assertSame('The Piano Guys', $rel->col(0)->value(-2));
        $this->assertSame('Metallica', $rel->col(0)->value(-3));
        try {
            $rel->col(0)->value(3);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }
        try {
            $rel->col(0)->value(-4);
            $this->fail();
        } catch (\OutOfBoundsException $e) {
        }

        $this->assertSame(
            [[1 => 'Black Album', 'S & M'], [1 => 'The Piano Guys'], [1 => 'Live One']],
            iterator_to_array($rel->col(1))
        );
    }

    public function testGetColumns()
    {
        $rel = $this->conn->query(
            "SELECT 1 AS a, 'foo' AS b, 3 AS c, 4 AS d"
        );
        $cols = $rel->getColumns();
        $this->assertEquals(4, count($cols));
        $this->assertSame(['a', 'b', 'c', 'd'], array_map(function (IColumn $c) { return $c->getName(); }, $cols));
        $this->assertSame([[1], ['foo'], [3], [4]], array_map(function (IColumn $c) { return $c->toArray(); }, $cols));
    }

    public function testExtend()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (point(0, 0), point(3, 4)), (point(1.3, 4), point(8, -2))) v ("A", "B")'
        );

        $ext = $rel->extend([
            'dist' => function (ITuple $tuple) {
                $a = $tuple->A;
                assert($a instanceof Point);
                $b = $tuple->B;
                assert($b instanceof Point);

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

        $this->assertEquals(5, $ext->tuple(0)->dist, '', 1e-9);
        $this->assertEquals(8.993886812719, $ext2->tuple(1)->dist, '', 1e-9);

        $this->assertEquals(Point::fromCoords(0, 4), $ext2->tuple(0)->upleft);
        $this->assertEquals(Point::fromCoords(1.3, 4), $ext2->tuple(1)->upleft);

        try {
            $c = $ext->col('upleft');
            $this->fail("Column {$c->getName()} should not have been defined");
        } catch (UndefinedColumnException $e) {
        }
    }

    public function testChaining()
    {
        $rel = $this->conn->query(
            'SELECT 1 AS a, 2 AS b, 3 AS c, 4 AS d'
        );
        $this->assertSame(
            [['n' => 2, 'z' => -3]],
            $rel->project(['x' => 'a', 'y' => 'b', 'z' => 'c'])
                ->rename(['x' => 'm', 'y' => 'n'])
                ->project([
                    'n',
                    'z' => function (ITuple $tuple) {
                        return -$tuple->z;
                    },
                ])
                ->toArray()
        );
    }

    public function testToSet()
    {
        $rel = $this->conn->query(
            'SELECT id, name, is_active FROM artist ORDER BY name, id'
        );

        $set = $rel->toSet('name');
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertFalse($set->contains('b-side band'));
        $this->assertFalse($set->contains('b-SIDE band'));

        $set = $rel->toSet('name', new CustomSet('strtolower'));
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertTrue($set->contains('b-side band'));
        $this->assertTrue($set->contains('b-SIDE band'));
    }

    public function testAmbiguousColumns()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT 1 AS a, 2 AS b, 3 AS b'
        );

        $this->assertSame([1, 2, 3], $tuple->toList());

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $map = $tuple->toMap();
            $this->fail(AmbiguousException::class . ' was expected');
        }
        catch (AmbiguousException $e) {
        }

        $this->assertSame(1, $tuple->a);

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $tuple->b;
            $this->fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }
}
