<?php
namespace Ivory\Relation;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\IConnection;
use Ivory\Data\Set\CustomSet;
use Ivory\Exception\AmbiguousException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Value\Composite;
use Ivory\Value\Box;
use Ivory\Value\Date;
use Ivory\Value\PgLogSequenceNumber;
use Ivory\Value\Point;
use Ivory\Value\TextSearchQuery;
use Ivory\Value\TextSearchVector;
use Ivory\Value\Time;
use Ivory\Value\TimeInterval;
use Ivory\Value\Timestamp;
use Ivory\Value\TimestampTz;
use Ivory\Value\TimeTz;
use Ivory\Value\TxIdSnapshot;

class RelationTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testBasic()
    {
        $rel = $this->conn->query(
            'SELECT id, name, is_active FROM artist WHERE id IN (1,2,3,4) ORDER BY name, id'
        );
        /** @var ITuple[] $tuples */
        $tuples = [];
        foreach ($rel as $tuple) {
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

        /** @var ITuple[] $tuples */
        $tuples = iterator_to_array($rel);
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
                /** @var Point $a */
                $a = $tuple->A;
                /** @var Point $b */
                $b = $tuple->B;
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

    public function testCompositeResult()
    {
        $rel = $this->conn->query(
            'SELECT artist.name AS artist_name, array_agg(album ORDER BY album.year) AS albums
             FROM artist
                  JOIN album_artist ON album_artist.artist_id = artist.id
                  JOIN album ON album.id = album_artist.album_id
             WHERE artist.id IN (1,2,3)
             GROUP BY artist.id
             ORDER BY artist.name'
        );

        $results = [];
        foreach ($rel as $tuple) {
            $albums = [];
            foreach ($tuple->albums as $album) {
                /** @var Composite $album */
                $albums[] = $album->toMap();
            }
            $results[$tuple->artist_name] = $albums;
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
            $rel->value('albums', 2)[1]->toMap()
        );
        $this->assertSame('Live One', $rel->value('albums', 2)[1][1]);
        $this->assertSame('Live One', $rel->value('albums', 2)[1]['name']);
        $this->assertSame('Live One', $rel->value('albums', 2)[1]->name);
    }

    public function testAnonymousCompositeResult()
    {
        $rel = $this->conn->query(
            "WITH a (k,l,m) AS (VALUES (1, 'a', true))
             SELECT a FROM a"
        );
        /** @var Composite $composite */
        $composite = $rel->value();
        // there does not seem to be a way to get associative array, as well as the types of components
        $this->assertSame(['1', 'a', 't'], $composite->toList());
    }

    public function testRangeResult()
    {
        $rel = $this->conn->query(
            "SELECT artist.name AS artist_name,
                    (CASE WHEN COUNT(album.*) > 0
                          THEN int4range(min(album.year), max(album.year), '[]')
                          ELSE 'empty'
                     END) AS active_years
             FROM artist
                  LEFT JOIN (album_artist
                             JOIN album ON album.id = album_artist.album_id
                            ) ON album_artist.artist_id = artist.id
             WHERE artist.id IN (1,2,3,4)
             GROUP BY artist.id
             ORDER BY artist.name"
        );
        $map = $rel->assoc('artist_name', 'active_years');

        $this->assertSame(['Metallica', 'Robbie Williams', 'The Piano Guys', 'Tommy Emmanuel'], $map->getKeys());

        $this->assertSame([1991, 1999], $map['Metallica']->toBounds('[]'));
        $this->assertTrue($map['Robbie Williams']->isEmpty());
        $this->assertSame([2012, 2012], $map['The Piano Guys']->toBounds('[]'));
        $this->assertSame([2005, 2005], $map['Tommy Emmanuel']->toBounds('[]'));
    }

    public function testBoxArrayResult()
    {
        $rel = $this->conn->query(
            "SELECT ARRAY[box(point(1,3),point(-3,5)), box(point(0,0),point(10,10)), box(point(1,2),point(3,4))]"
        );
        $areaSum = 0;
        foreach ($rel->value() as $box) {
            /** @var Box $box */
            $areaSum += $box->getArea();
        }
        $this->assertEquals(112, $areaSum, '', 1e-9);
    }

    public function testDateResult()
    {
        $rel = $this->conn->query(
            'SELECT name, released
             FROM album
             ORDER BY released, name'
        );

        $list = $rel->toArray();
        $this->assertEquals(['name' => 'Black Album', 'released' => Date::fromParts(1991, 8, 12)], $list[0]);
        $this->assertEquals(['name' => 'S & M', 'released' => Date::fromParts(1999, 11, 23)], $list[1]);
        $this->assertEquals(['name' => 'Live One', 'released' => Date::fromParts(2005, 1, 1)], $list[2]);
        $this->assertEquals(['name' => 'The Piano Guys', 'released' => Date::fromParts(2012, 10, 2)], $list[3]);

        $this->conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            foreach ($dateStyles as $dateStyle) {
                $this->conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                $rel = $this->conn->query(
                    "SELECT '2016-04-17'::DATE as d,
                            '1987-08-17 BC'::DATE AS bc,
                            'infinity'::DATE AS inf,
                            '-infinity'::DATE AS minus_inf,
                            '5874897-12-31'::DATE AS mx,
                            '4713-01-01 BC'::DATE AS mn"
                );
                $tuple = $rel->tuple();
                $this->assertEquals(Date::fromParts(2016, 4, 17), $tuple->d, $dateStyle);
                $this->assertEquals(Date::fromParts(-1987, 8, 17), $tuple->bc, $dateStyle);
                $this->assertEquals(Date::infinity(), $tuple->inf, $dateStyle);
                $this->assertEquals(Date::minusInfinity(), $tuple->minus_inf, $dateStyle);
                $this->assertEquals(Date::fromParts(5874897, 12, 31), $tuple->mx, $dateStyle);
                $this->assertEquals(Date::fromParts(-4713, 1, 1), $tuple->mn, $dateStyle);
            }
        } finally {
            $this->conn->rollback();
        }
    }

    public function testDateRangeResult()
    {
        $rel = $this->conn->query(
            "SELECT artist.name, daterange(MIN(album.released), MAX(album.released), '[]') AS activerng
             FROM artist
                  JOIN album_artist aa ON aa.artist_id = artist.id
                  JOIN album ON album.id = aa.album_id
             WHERE artist.id IN (1,2,3,4)
             GROUP BY artist.id
             ORDER BY artist.name"
        );

        $this->assertSame(['Metallica', 'The Piano Guys', 'Tommy Emmanuel'], $rel->col('name')->toArray());

        $this->assertEquals(
            [Date::fromParts(1991, 8, 12), Date::fromParts(1999, 11, 23)],
            $rel->value('activerng', 0)->toBounds('[]')
        );

        $this->assertEquals(
            [Date::fromParts(2012, 10, 2), Date::fromParts(2012, 10, 2)],
            $rel->value('activerng', 1)->toBounds('[]')
        );

        $this->assertEquals(
            [Date::fromParts(2005, 1, 1), Date::fromParts(2005, 1, 1)],
            $rel->value('activerng', 2)->toBounds('[]')
        );
    }

    public function testTimeResult()
    {
        $rel = $this->conn->query(
            "SELECT '0:00'::TIME AS midnight,
                    '07:59:60.123456'::TIME AS leap_sec,
                    '15:42:54'::TIME AS pm,
                    '24:00:00'::TIME AS next_midnight"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(Time::fromPartsStrict(0, 0, 0), $tuple->midnight);
        $this->assertEquals(Time::fromPartsStrict(8, 0, .123456), $tuple->leap_sec);
        $this->assertEquals(Time::fromPartsStrict(15, 42, 54), $tuple->pm);
        $this->assertEquals(Time::fromPartsStrict(24, 0, 0), $tuple->next_midnight);
    }

    public function testTimeTzResult()
    {
        $rel = $this->conn->query(
            "SELECT '0:00+0000'::TIMETZ AS midnight,
                    '07:59:60.123456+13:30'::TIMETZ AS leap_sec,
                    '15:42:54-08:00'::TIMETZ AS pm,
                    '24:00:00+0600'::TIMETZ AS next_midnight"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(TimeTz::fromPartsStrict(0, 0, 0, 0), $tuple->midnight);
        $this->assertEquals(TimeTz::fromPartsStrict(8, 0, .123456, 48600), $tuple->leap_sec);
        $this->assertEquals(TimeTz::fromPartsStrict(15, 42, 54, -28800), $tuple->pm);
        $this->assertEquals(TimeTz::fromPartsStrict(24, 0, 0, 21600), $tuple->next_midnight);
    }

    public function testTimestampResult()
    {
        $this->conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            foreach ($dateStyles as $dateStyle) {
                $this->conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                $rel = $this->conn->query(
                    "SELECT '2016-04-17 0:00'::TIMESTAMP AS midnight,
                            '2016-05-17 07:59:60.123456'::TIMESTAMP AS leap_sec,
                            '2016-06-17 15:42:54'::TIMESTAMP AS pm,
                            '2016-07-17 24:00:00'::TIMESTAMP AS next_midnight,
                            '1987-08-17 13:01:02.5 BC'::TIMESTAMP AS bc,
                            'infinity'::TIMESTAMP AS inf,
                            '-infinity'::TIMESTAMP AS minus_inf,
                            '294276-12-31 24:00:00'::TIMESTAMP AS mx,
                            '4713-01-01 00:00:00 BC'::TIMESTAMP AS mn"
                );
                $tuple = $rel->tuple();
                $this->assertEquals(Timestamp::fromParts(2016, 4, 17, 0, 0, 0), $tuple->midnight, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 5, 17, 8, 0, 0.123456), $tuple->leap_sec, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 6, 17, 15, 42, 54), $tuple->pm, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 7, 18, 0, 0, 0), $tuple->next_midnight, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(-1987, 8, 17, 13, 1, 2.5), $tuple->bc, $dateStyle);
                $this->assertEquals(Timestamp::infinity(), $tuple->inf, $dateStyle);
                $this->assertEquals(Timestamp::minusInfinity(), $tuple->minus_inf, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(294277, 1, 1, 0, 0, 0), $tuple->mx, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(-4713, 1, 1, 0, 0, 0), $tuple->mn, $dateStyle);
            }
        } finally {
            $this->conn->rollback();
        }
    }

    public function testTimestampTzResult()
    {
        $this->conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            $timeZones = [
                'UTC' => [['+0000', 'UTC'], ['+0000', 'UTC'], '+0000'],
                'Europe/Prague' => [['+0100', 'CET'], ['+0200', 'CEST'], '+0057'],
            ];
            foreach ($dateStyles as $dateStyle) {
                $this->conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                foreach ($timeZones as $tz => list($tzStdSpec, $tzSmrSpec, $lmtOffset)) {
                    $tzStd = ($dateStyle == 'ISO' ? $tzStdSpec[0] : $tzStdSpec[1]);
                    $tzSmr = ($dateStyle == 'ISO' ? $tzSmrSpec[0] : $tzSmrSpec[1]);
                    $msg = "$dateStyle; $tz";

                    $this->conn->getConfig()->setForTransaction(ConfigParam::TIME_ZONE, $tz);
                    $rel = $this->conn->query(
                        "SELECT '2016-01-17 0:00'::TIMESTAMPTZ AS midnight,
                                '2016-06-17 15:42:54'::TIMESTAMPTZ AS pm,
                                '2016-07-17 24:00:00'::TIMESTAMPTZ AS next_midnight,
                                '2016-05-17 07:59:60.123456'::TIMESTAMPTZ AS leap_sec,
                                'infinity'::TIMESTAMPTZ AS inf,
                                '-infinity'::TIMESTAMPTZ AS minus_inf,
                                '1987-08-17 13:01:02.5 BC'::TIMESTAMPTZ AS bc,
                                '294276-12-31 24:00:00'::TIMESTAMPTZ AS mx,
                                '4713-01-01 00:00:00 BC'::TIMESTAMPTZ AS mn"
                    );

                    $this->errorNonInterruptMode(E_USER_WARNING);
                    $tuple = $rel->tuple();
                    $this->errorInterruptMode();
                    if ($dateStyle == 'ISO' && $tz != 'UTC') {
                        $this->assertErrorsTriggered(2, "~Cutting '\\+00:57:44' to '\\+00:57'~", E_ALL, $msg);
                    }
                    $this->assertNoMoreErrors($msg);

                    $this->assertEquals(TimestampTz::fromParts(2016, 1, 17, 0, 0, 0, $tzStd), $tuple->midnight, $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 6, 17, 15, 42, 54, $tzSmr), $tuple->pm, $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 7, 18, 0, 0, 0, $tzSmr), $tuple->next_midnight,
                        $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 5, 17, 8, 0, 0.123456, $tzSmr), $tuple->leap_sec,
                        $msg);
                    $this->assertEquals(TimestampTz::infinity(), $tuple->inf, $msg);
                    $this->assertEquals(TimestampTz::minusInfinity(), $tuple->minus_inf, $msg);
                    $this->assertEquals(TimestampTz::fromParts(-1987, 8, 17, 13, 1, 2.5, $lmtOffset), $tuple->bc,
                        $msg);
                    $this->assertEquals(TimestampTz::fromParts(294277, 1, 1, 0, 0, 0, $tzStd), $tuple->mx, $msg);
                    $this->assertEquals(TimestampTz::fromParts(-4713, 1, 1, 0, 0, 0, $lmtOffset), $tuple->mn, $msg);
                }
            }
        } finally {
            $this->conn->rollback();
        }
    }

    public function testPointsResult()
    {
        $rel = $this->conn->query(
            'SELECT *
             FROM (VALUES (point(0, 0), point(3, 4)), (point(1.3, 4), point(8, -2))) v ("A", "B")'
        );
        $this->assertSame(2, count($rel));
        $this->assertEquals(Point::fromCoords(0, 0), $rel->tuple(0)->A);
        $this->assertEquals(Point::fromCoords(0, 0), $rel->value('A', 0));
        $this->assertEquals(
            [
                ['A' => Point::fromCoords(0, 0), 'B' => Point::fromCoords(3, 4)],
                ['A' => Point::fromCoords(1.3, 4), 'B' => Point::fromCoords(8, -2)],
            ],
            $rel->toArray()
        );
    }

    public function testPgLsnResult()
    {
        $rel = $this->conn->query(
            "SELECT '16/B374D848'::pg_lsn,
                    '0/0'::pg_lsn,
                    'FFFFFFFF/FFFFFFFF'::pg_lsn"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(PgLogSequenceNumber::fromString('16/B374D848'), $tuple[0]);
        $this->assertEquals(PgLogSequenceNumber::fromString('0/0'), $tuple[1]);
        $this->assertEquals(PgLogSequenceNumber::fromString('FFFFFFFF/FFFFFFFF'), $tuple[2]);
    }

    public function testTxidSnapshotResult()
    {
        $rel = $this->conn->query(
            "SELECT '10:20:10,14,15'::txid_snapshot,
                    '10:20:19'::txid_snapshot,
                    '10:20:'::txid_snapshot,
                    '10:10:'::txid_snapshot"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(TxIdSnapshot::fromParts(10, 20, [10, 14, 15]), $tuple[0]);
        $this->assertEquals(TxIdSnapshot::fromParts(10, 20, [19]), $tuple[1]);
        $this->assertEquals(TxIdSnapshot::fromParts(10, 20, []), $tuple[2]);
        $this->assertEquals(TxIdSnapshot::fromParts(10, 10, []), $tuple[3]);
    }

    public function testTsVectorResult()
    {
        $rel = $this->conn->query(
            "SELECT 'a fat cat sat on a mat and ate a fat rat'::tsvector AS list,
                    \$\$the lexeme '    ' contains spaces\$\$::tsvector AS whitespace,
                    \$\$the lexeme 'Joe''s' contains a quote\$\$::tsvector AS quotes,
                    'a:1 fat:2 cat:3 sat:4 on:5 a:6 mat:7 and:8 ate:9 a:10 fat:11 rat:12'::tsvector AS positioned,
                    'a:1A fat:2B,4C cat:5D'::tsvector AS weighted"
        );

        $tuple = $rel->tuple();

        $this->assertEquals(
            TextSearchVector::fromSet(['a', 'and', 'ate', 'cat', 'fat', 'mat', 'on', 'rat', 'sat']),
            $tuple->list
        );

        $this->assertEquals(
            TextSearchVector::fromSet(['    ', 'contains', 'lexeme', 'spaces', 'the']),
            $tuple->whitespace
        );

        $this->assertEquals(
            TextSearchVector::fromSet(['a', 'contains', "Joe's", 'lexeme', 'quote', 'the']),
            $tuple->quotes
        );

        $this->assertEquals(
            TextSearchVector::fromList(['a', 'fat', 'cat', 'sat', 'on', 'a', 'mat', 'and', 'ate', 'a', 'fat', 'rat']),
            $tuple->positioned
        );

        $this->assertEquals(
            TextSearchVector::fromMap(['a' => [['1', 'A']], 'fat' => [[2, 'B'], [4, 'C']], 'cat' => [[5, 'D']]]),
            $tuple->weighted
        );
    }

    public function testTsQueryResult()
    {
        $rel = $this->conn->query(
            "SELECT '!fat & (rat:ab | cat)'::tsquery AS ops,
                    'super:*'::tsquery AS star,
                    \$\$'Jo  e''s'''\$\$::tsquery AS quotes"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(TextSearchQuery::fromString("!'fat' & ( 'rat':AB | 'cat' )"), $tuple->ops);
        $this->assertEquals(TextSearchQuery::fromString("'super':*"), $tuple->star);
        $this->assertEquals(TextSearchQuery::fromString("'Jo  e''s'''"), $tuple->quotes);
    }

    public function testIntervalResult()
    {
        $rel = $this->conn->query(
            "SELECT 'P1Y2M3W3DT4H5M6S'::interval AS iso,
                    'P0001-02-03T04:05:06'::interval AS iso_alt,
                    '1-2'::interval AS sql_year_mon,
                    '3 12:34:56'::interval AS sql_day_time,
                    '4 days ago'::interval AS pg"
        );

        $tuple = $rel->tuple();
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::WEEK => 3, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6,
            ]),
            $tuple->iso
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6,
            ]),
            $tuple->iso_alt
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 2]),
            $tuple->sql_year_mon
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::DAY => 3,
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34, TimeInterval::SECOND => 56,
            ]),
            $tuple->sql_day_time
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => -4]),
            $tuple->pg
        );
    }

    public function testAmbiguousColumns()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT 1 AS a, 2 AS b, 3 AS b'
        );

        $this->assertSame([1, 2, 3], $tuple->toList());

        try {
            $map = $tuple->toMap();
            $this->fail(AmbiguousException::class . ' was expected');
        }
        catch (AmbiguousException $e) {
        }

        $this->assertSame(1, $tuple->a);

        try {
            $val = $tuple->b;
            $this->fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }
}
