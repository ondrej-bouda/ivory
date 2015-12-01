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

        $this->assertSame($expArray, $qr->toArray());
    }

    public function testResultAccess()
    {
        $query = 'SELECT artist.name AS artist, array_agg(album.name ORDER BY album.year) AS album_names
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
            ['artist' => 'Metallica', 'album_names' => [1 => 'Black Album', 'S & M']],
            ['artist' => 'The Piano Guys', 'album_names' => [1 => 'The Piano Guys']],
            ['artist' => 'Tommy Emmanuel', 'album_names' => [1 => 'Live One']],
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
        $this->assertSame('The Piano Guys', $qr->value('artist', 1));

        $evaluator = function (ITuple $tuple) {
            return sprintf('%s (%d)', $tuple['artist'][0], count($tuple['album_names']));
        };
        $this->assertSame('T (1)', $qr->value($evaluator, 1));

        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $qr->col(0)->toArray()
        );
        $this->assertSame(
            ['Metallica', 'The Piano Guys', 'Tommy Emmanuel'],
            $qr->col('artist')->toArray()
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
            'SELECT artist.name AS artist, array_agg(album ORDER BY album.year) AS albums
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             GROUP BY artist.id
             ORDER BY artist.name'
        );

        $this->assertSame(
            ['id' => 4, 'name' => 'Live One', 'year' => 2005],
            $qr->value('albums', 2)[1]->toMap()
        );
        $this->assertSame('Live One', $qr->value('albums', 2)[1][1]);
        $this->assertSame('Live One', $qr->value('albums', 2)[1]['name']);
        $this->assertSame('Live One', $qr->value('albums', 2)[1]->name);
    }
}
