<?php
namespace Ivory\Relation;

use Ivory\Value\Composite;
use Ivory\Value\Box;
use Ivory\Value\Date;
use Ivory\Value\Range;

class QueryRelationTest extends \Ivory\IvoryTestCase
{
    public function testDummy()
    {
        $this->assertEquals(4, $this->getConnection()->getRowCount('artist'));
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
            ['id' => 4, 'name' => 'B-Side Band', 'is_active' => null],
            ['id' => 2, 'name' => 'Metallica', 'is_active' => false],
            ['id' => 1, 'name' => 'The Piano Guys', 'is_active' => true],
            ['id' => 3, 'name' => 'Tommy Emmanuel', 'is_active' => null],
        ];

        $this->assertSame($expArray, $qr->toArray());
    }

    public function testResultAccess()
    {
        $query = 'SELECT artist.name AS artist_name, array_agg(album.name ORDER BY album.year) AS album_names
                  FROM artist
                       JOIN album_artist ON album_artist.artist_id = artist.id
                       JOIN album ON album.id = album_artist.album_id
                  GROUP BY artist.id
                  ORDER BY artist.name';
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn, $query);

        $this->assertSame($query, $qr->getSql());

        /** @var ITuple[] $tuples */
        $tuples = iterator_to_array($qr);
        $expArray = [
            ['artist_name' => 'Metallica', 'album_names' => [1 => 'Black Album', 'S & M']],
            ['artist_name' => 'The Piano Guys', 'album_names' => [1 => 'The Piano Guys']],
            ['artist_name' => 'Tommy Emmanuel', 'album_names' => [1 => 'Live One']],
        ];

        $this->assertSame(count($expArray), count($qr));
        $this->assertSame(count($expArray), count($tuples));
        $this->assertSame(count($expArray), count($tuples));

        $this->assertSame($expArray[0], $tuples[0]->toMap());
        $this->assertSame($expArray[1], $tuples[1]->toMap());
        $this->assertSame($expArray[2], $tuples[2]->toMap());

        $this->assertSame($expArray, $qr->toArray());

        $this->assertSame('S & M', $qr->value(1)[2]);
        $this->assertSame('S & M', $qr->value('album_names')[2]);
        $this->assertSame('The Piano Guys', $qr->value(0, 1));
        $this->assertSame('The Piano Guys', $qr->value('artist_name', 1));

        $evaluator = function (ITuple $tuple) {
            return sprintf('%s (%d)', $tuple['artist_name'][0], count($tuple['album_names']));
        };
        $this->assertSame('T (1)', $qr->value($evaluator, 1));

        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $qr->col(0)->toArray()
        );
        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $qr->col('artist_name')->toArray()
        );
        $this->assertSame(
            ['M (2)', 'T (1)', 'T (1)'],
            $qr->col($evaluator)->toArray()
        );

        $this->assertSame(
            [[1 => 'Black Album', 'S & M'], [1 => 'The Piano Guys'], [1 => 'Live One']],
            iterator_to_array($qr->col(1))
        );
    }

    public function testCompositeResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'SELECT artist.name AS artist_name, array_agg(album ORDER BY album.year) AS albums
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             GROUP BY artist.id
             ORDER BY artist.name'
        );

        $results = [];
        foreach ($qr as $tuple) {
            $albums = [];
            foreach ($tuple['albums'] as $album) {
                /** @var Composite $album */
                $albums[] = $album->toMap();
            }
            $results[$tuple['artist_name']] = $albums;
        }
        $this->assertEquals(
            [
                'Metallica' => [
                    ['id' => 2, 'name' => 'Black Album', 'year' => 1991, 'released' => Date::fromISOString('1991-08-12')],
                    ['id' => 3, 'name' => 'S & M', 'year' => 1999, 'released' => Date::fromISOString('1999-11-23')],
                ],
                'The Piano Guys' => [
                    ['id' => 1, 'name' => 'The Piano Guys', 'year' => 2012, 'released' => Date::fromISOString('2012-10-02')],
                ],
                'Tommy Emmanuel' => [
                    ['id' => 4, 'name' => 'Live One', 'year' => 2005, 'released' => Date::fromISOString('2005-01-01')],
                ],
            ],
            $results
        );

        $this->assertEquals(
            ['id' => 4, 'name' => 'Live One', 'year' => 2005, 'released' => Date::fromISOString('2005-01-01')],
            $qr->value('albums', 2)[1]->toMap()
        );
        $this->assertSame('Live One', $qr->value('albums', 2)[1][1]);
        $this->assertSame('Live One', $qr->value('albums', 2)[1]['name']);
        $this->assertSame('Live One', $qr->value('albums', 2)[1]->name);
    }

    public function testRangeResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT artist.name AS artist_name,
                    (CASE WHEN COUNT(album.*) > 0
                          THEN int4range(min(album.year), max(album.year), '[]')
                          ELSE 'empty'
                     END) AS active_years
             FROM artist
                  LEFT JOIN (album_artist
                             JOIN album ON album.id = album_artist.album_id
                            ) ON album_artist.artist_id = artist.id
             GROUP BY artist.id
             ORDER BY artist.name"
        );

        /** @var Range[] $map */
        $map = [];
        foreach ($qr as $row) {
            $map[$row['artist_name']] = $row['active_years'];
        }
        // TODO: refactor to the following code after implementing assoc()
//        $map = $qr->assoc('artist_name', 'active_years');

        $this->assertSame(['B-Side Band', 'Metallica', 'The Piano Guys', 'Tommy Emmanuel'], array_keys($map));

        $this->assertTrue($map['B-Side Band']->isEmpty());
        $this->assertSame([1991, 1999], $map['Metallica']->toBounds('[]'));
        $this->assertSame([2012, 2012], $map['The Piano Guys']->toBounds('[]'));
        $this->assertSame([2005, 2005], $map['Tommy Emmanuel']->toBounds('[]'));
    }

    public function testBoxArrayResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT ARRAY[box(point(1,3),point(-3,5)), box(point(0,0),point(10,10)), box(point(1,2),point(3,4))]"
        );
        $areaSum = 0;
        foreach ($qr->value() as $box) {
            /** @var Box $box */
            $areaSum += $box->getArea();
        }
        $this->assertEquals(112, $areaSum, '', 1e-9);
    }

    public function testDateResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT name, released
             FROM album
             ORDER BY released, name"
        );

        $list = $qr->toArray();
        $this->assertEquals(['name' => 'Black Album', 'released' => Date::fromParts(1991, 8, 12)], $list[0]);
        $this->assertEquals(['name' => 'S & M', 'released' => Date::fromParts(1999, 11, 23)], $list[1]);
        $this->assertEquals(['name' => 'Live One', 'released' => Date::fromParts(2005, 1, 1)], $list[2]);
        $this->assertEquals(['name' => 'The Piano Guys', 'released' => Date::fromParts(2012, 10, 2)], $list[3]);
    }

    public function testDateRangeResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT artist.name, daterange(MIN(album.released), MAX(album.released), '[]') AS activerng
             FROM artist
                  JOIN album_artist aa ON aa.artist_id = artist.id
                  JOIN album ON album.id = aa.album_id
             GROUP BY artist.id
             ORDER BY artist.name"
        );

        $this->assertSame(['Metallica', 'The Piano Guys', 'Tommy Emmanuel'], $qr->col('name')->toArray());

        $this->assertEquals(
            [Date::fromParts(1991, 8, 12), Date::fromParts(1999, 11, 23)],
            $qr->value('activerng', 0)->toBounds('[]')
        );

        $this->assertEquals(
            [Date::fromParts(2012, 10, 2), Date::fromParts(2012, 10, 2)],
            $qr->value('activerng', 1)->toBounds('[]')
        );

        $this->assertEquals(
            [Date::fromParts(2005, 1, 1), Date::fromParts(2005, 1, 1)],
            $qr->value('activerng', 2)->toBounds('[]')
        );
    }
}
