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
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            'SELECT artist.name AS artist, array_agg(album.name ORDER BY album.year) AS albums
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             GROUP BY artist.id
             ORDER BY artist.name'
        );
        /** @var ITuple[] $tuples */
        $tuples = iterator_to_array($qr);
        $expArray = [
            ['artist' => 'Metallica', 'albums' => [1 => 'Black Album', 'S & M']],
            ['artist' => 'The Piano Guys', 'albums' => [1 => 'The Piano Guys']],
            ['artist' => 'Tommy Emmanuel', 'albums' => [1 => 'Live One']],
        ];

        $this->assertSame(count($expArray), count($qr));
        $this->assertSame(count($expArray), count($tuples));
        $this->assertSame(count($expArray), count($tuples));

        $this->assertSame($expArray[0], $tuples[0]->toMap());
        $this->assertSame($expArray[1], $tuples[1]->toMap());
        $this->assertSame($expArray[2], $tuples[2]->toMap());

        $this->assertSame($expArray, $qr->toArray());

        $this->assertSame('S & M', $qr->value(1)[2]);
        $this->assertSame('S & M', $qr->value('albums')[2]);
        $this->assertSame('The Piano Guys', $qr->value(0, 1));
        $this->assertSame('The Piano Guys', $qr->value('artist', 1));
        $this->assertSame('T', $qr->value(function (ITuple $tuple) { return $tuple['artist'][0]; }, 1));
    }
}
