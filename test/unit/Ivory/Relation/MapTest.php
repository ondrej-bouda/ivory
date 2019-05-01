<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Data\Map\ITupleMap;
use Ivory\Exception\UndefinedColumnException;
use Ivory\IvoryTestCase;
use PHPUnit\Framework\Error\Warning;

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

    protected function setUp(): void
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

        self::assertSame('Black Album', $map['Metallica']->album);
        self::assertSame('Black Album', $map->get('Metallica')->value('album'));
        self::assertSame(
            ['artist' => 'Metallica', 'year' => 1991, 'album' => 'Black Album'],
            $map->get('Metallica')->toMap()
        );

        try {
            $map->get('wheee');
            self::fail();
        } catch (\OutOfBoundsException $exception) {
        }

        self::assertSame('Black Album', $map->maybe('Metallica')->value('album'));
        self::assertNull($map->maybe('wheee'));
    }

    public function testAccessMultiLevel()
    {
        $map = $this->artistYearRel->map('artist', 'year');

        $album = $map['Metallica'][1999];
        assert($album instanceof ITuple);
        self::assertSame('S & M', $album->value('album'));

        self::assertSame('S & M', $map->get('Metallica')[1999]->value('album'));
        self::assertSame('S & M', $map->get('Metallica')->get(1999)->value('album'));
        self::assertSame('S & M', $map->get('Metallica', 1999)->value('album'));
        self::assertSame('Black Album', $map['Metallica'][1991]->album);

        try {
            $map->get('wheee');
            self::fail();
        } catch (\OutOfBoundsException $exception) {
        }

        try {
            $map->get('Metallica', 'wheee');
            self::fail();
        } catch (\OutOfBoundsException $exception) {
        }

        self::assertSame('S & M', $map->maybe('Metallica')->maybe(1999)->value('album'));
        self::assertSame('S & M', $map->maybe('Metallica', 1999)->value('album'));
        self::assertNull($map->maybe('wheee'));
        self::assertNull($map->maybe('Metallica', 'wheee'));
        self::assertNull($map->get('Metallica')->maybe('wheee'));
    }

    public function testCount()
    {
        $map = $this->artistRel->map('artist');
        self::assertSame(4, $map->count());
        self::assertSame(4, count($map));

        $map = $this->yearRel->map('year');
        self::assertSame(5, $map->count());
        self::assertSame(5, count($map));

        $map = $this->artistYearRel->map('artist', 'year');
        self::assertSame(4, $map->count());
        self::assertSame(4, count($map));

        $map = $this->artistYearRel->map('artist', 'year');
        self::assertSame(2, $map->get('Metallica')->count());
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
            self::fail('A warning expected due to multiple tuples mapping to the same key.');
        } catch (Warning $e) {
            $expDesc = implode(', ', $dupKeys);
            self::assertContains($expDesc, $e->getMessage());
        }
    }

    public function testGetKeys()
    {
        $map = $this->artistRel->map('artist');
        self::assertSame(
            ['The Piano Guys', 'Metallica', 'Tommy Emmanuel', 'B-Side Band'],
            $map->getKeys()
        );

        $map = $this->artistYearRel->map('artist', 'year');
        self::assertSame(
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
            assert($tuple instanceof ITuple);
            list($expKey, $expVals) = $expected[$i];
            self::assertSame($expKey, $key);
            self::assertSame($expVals, $tuple->toMap());
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
            assert($inner instanceof ITupleMap);
            list($expKey, $expTuples) = $expected[$i];
            self::assertSame($expKey, $key);
            $j = 0;
            foreach ($inner as $innerKey => $tuple) {
                assert($tuple instanceof ITuple);
                list($expInnerKey, $expVals) = $expTuples[$j];
                self::assertSame($expInnerKey, $innerKey);
                self::assertSame($expVals, $tuple->toMap());
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
