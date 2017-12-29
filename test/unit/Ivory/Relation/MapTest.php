<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Data\Map\ITupleMap;
use Ivory\Exception\UndefinedColumnException;
use Ivory\IvoryTestCase;

class MapTest extends IvoryTestCase
{
    /** @var IRelation relation with all tuples */
    private $fullRel;
    /** @var IRelation relation with distinct artists */
    private $artistRel;
    /** @var IRelation relation with distinct album years */
    private $yearRel;
    /** @var IRelation relation with distinct artist-album years */
    private $artistYearRel;

    private function createRel($distinctFields = [])
    {
        $conn = $this->getIvoryConnection();

        if ($distinctFields) {
            $distinct = sprintf('DISTINCT ON (%s)', implode(',', $distinctFields));
            $orderBy = implode(', ', $distinctFields);
        } else {
            $distinct = '';
            $orderBy = '1';
        }

        return $conn->query(
            "SELECT %sql:distinct
                    artist.name AS artist,
                    album.year,
                    album.name AS album
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             WHERE artist.id IN (1,2,3,4,5)
             ORDER BY %sql:order, artist.id, album.year, album.name",
            [
                'distinct' => $distinct,
                'order' => $orderBy,
            ]
        );
    }

    protected function setUp()
    {
        parent::setUp();

        $this->fullRel = $this->createRel();
        $this->artistRel = $this->createRel(['artist.id']);
        $this->yearRel = $this->createRel(['year']);
        $this->artistYearRel = $this->createRel(['artist.id', 'year']);
    }

    public function testAccessSingleLevel()
    {
        $map = $this->artistRel->map('artist');

        $this->assertSame('Black Album', $map['Metallica']->album);
        $this->assertSame('Black Album', $map->get('Metallica')->value('album'));
        $this->assertSame(
            ['artist' => 'Metallica', 'year' => 1991, 'album' => 'Black Album'],
            $map->get('Metallica')->toMap()
        );

        try {
            $map->get('wheee');
            $this->fail();
        } catch (\OutOfBoundsException $exception) {
        }

        $this->assertSame('Black Album', $map->maybe('Metallica')->value('album'));
        $this->assertNull($map->maybe('wheee'));
    }

    public function testAccessMultiLevel()
    {
        $map = $this->artistYearRel->map('artist', 'year');

        $album = $map['Metallica'][1999];
        assert($album instanceof ITuple);
        $this->assertSame('S & M', $album->value('album'));

        $this->assertSame('S & M', $map->get('Metallica')[1999]->value('album'));
        $this->assertSame('S & M', $map->get('Metallica')->get(1999)->value('album'));
        $this->assertSame('S & M', $map->get('Metallica', 1999)->value('album'));
        $this->assertSame('Black Album', $map['Metallica'][1991]->album);

        try {
            $map->get('wheee');
            $this->fail();
        } catch (\OutOfBoundsException $exception) {
        }

        try {
            $map->get('Metallica', 'wheee');
            $this->fail();
        } catch (\OutOfBoundsException $exception) {
        }

        $this->assertSame('S & M', $map->maybe('Metallica')->maybe(1999)->value('album'));
        $this->assertSame('S & M', $map->maybe('Metallica', 1999)->value('album'));
        $this->assertNull($map->maybe('wheee'));
        $this->assertNull($map->maybe('Metallica', 'wheee'));
        $this->assertNull($map->get('Metallica')->maybe('wheee'));
    }

    public function testCount()
    {
        $map = $this->artistRel->map('artist');
        $this->assertSame(4, $map->count());
        $this->assertSame(4, count($map));

        $map = $this->yearRel->map('year');
        $this->assertSame(5, $map->count());
        $this->assertSame(5, count($map));

        $map = $this->artistYearRel->map('artist', 'year');
        $this->assertSame(4, $map->count());
        $this->assertSame(4, count($map));

        $map = $this->artistYearRel->map('artist', 'year');
        $this->assertSame(2, $map->get('Metallica')->count());
    }

    public function testDuplicityHandling()
    {
        $this->expectMappingWarning(['B-Side Band'], 'artist');
        $this->expectMappingWarning(['B-Side Band', 2014], 'artist', 'year');
    }

    private function expectMappingWarning(array $dupKeys, ...$mappingCols)
    {
        try {
            $this->fullRel->map(...$mappingCols);
            $this->fail('A warning expected due to multiple tuples mapping to the same key.');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            $expDesc = implode(', ', $dupKeys);
            $this->assertContains($expDesc, $e->getMessage());
        }
    }

    public function testGetKeys()
    {
        $map = $this->artistRel->map('artist');
        $this->assertSame(
            ['The Piano Guys', 'Metallica', 'Tommy Emmanuel', 'B-Side Band'],
            $map->getKeys()
        );

        $map = $this->artistYearRel->map('artist', 'year');
        $this->assertSame(
            [1991, 1999],
            $map->get('Metallica')->getKeys()
        );
    }

    public function testTraversalSingleLevel()
    {
        $map = $this->artistRel->map('artist');

        /** @var array $expected list: pair: key, tuple values array */
        $expected = [
            ['The Piano Guys', ['artist' => 'The Piano Guys', 'year' => 2012, 'album' => 'The Piano Guys']],
            ['Metallica', ['artist' => 'Metallica', 'year' => 1991, 'album' => 'Black Album']],
            ['Tommy Emmanuel', ['artist' => 'Tommy Emmanuel', 'year' => 2005, 'album' => 'Live One']],
            ['B-Side Band', ['artist' => 'B-Side Band', 'year' => 2014, 'album' => 'Deska']],
        ];
        $i = 0;
        foreach ($map as $key => $tuple) {
            /** @var ITuple $tuple */
            list($expKey, $expVals) = $expected[$i];
            $this->assertSame($expKey, $key);
            $this->assertSame($expVals, $tuple->toMap());
            $i++;
        }
    }

    public function testTraversalMultiLevel()
    {
        $map = $this->artistYearRel->map('artist', 'year');

        /** @var array $expected list: list: pair: key, tuple values array */
        $expected = [
            ['The Piano Guys', [
                [2012, ['artist' => 'The Piano Guys', 'year' => 2012, 'album' => 'The Piano Guys']],
            ]],
            ['Metallica', [
                [1991, ['artist' => 'Metallica', 'year' => 1991, 'album' => 'Black Album']],
                [1999, ['artist' => 'Metallica', 'year' => 1999, 'album' => 'S & M']],
            ]],
            ['Tommy Emmanuel', [
                [2005, ['artist' => 'Tommy Emmanuel', 'year' => 2005, 'album' => 'Live One']],
            ]],
            ['B-Side Band', [
                [2014, ['artist' => 'B-Side Band', 'year' => 2014, 'album' => 'Deska']],
            ]],
        ];
        $i = 0;
        foreach ($map as $key => $inner) {
            /** @var ITupleMap $inner */
            list($expKey, $expTuples) = $expected[$i];
            $this->assertSame($expKey, $key);
            $j = 0;
            foreach ($inner as $innerKey => $tuple) {
                /** @var ITuple $tuple */
                list($expInnerKey, $expVals) = $expTuples[$j];
                $this->assertSame($expInnerKey, $innerKey);
                $this->assertSame($expVals, $tuple->toMap());
                $j++;
            }
            $i++;
        }
    }

    public function testUndefinedColumn()
    {
        $this->expectException(UndefinedColumnException::class);
        $this->artistRel->map('nonexistent');
    }
}
