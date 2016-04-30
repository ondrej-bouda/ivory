<?php
namespace Ivory\Relation;

use Ivory\Connection\ConfigParam;
use Ivory\Value\Composite;
use Ivory\Value\Box;
use Ivory\Value\Date;
use Ivory\Value\PgLogSequenceNumber;
use Ivory\Value\Timestamp;
use Ivory\Value\Range;
use Ivory\Value\Time;
use Ivory\Value\TimestampTz;
use Ivory\Value\TimeTz;

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
            'SELECT name, released
             FROM album
             ORDER BY released, name'
        );

        $list = $qr->toArray();
        $this->assertEquals(['name' => 'Black Album', 'released' => Date::fromParts(1991, 8, 12)], $list[0]);
        $this->assertEquals(['name' => 'S & M', 'released' => Date::fromParts(1999, 11, 23)], $list[1]);
        $this->assertEquals(['name' => 'Live One', 'released' => Date::fromParts(2005, 1, 1)], $list[2]);
        $this->assertEquals(['name' => 'The Piano Guys', 'released' => Date::fromParts(2012, 10, 2)], $list[3]);

        $conn = $this->getIvoryConnection();
        $conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            foreach ($dateStyles as $dateStyle) {
                $conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                $qr = new QueryRelation($conn,
                    "SELECT '2016-04-17'::DATE as d,
                            '1987-08-17 BC'::DATE AS bc,
                            'infinity'::DATE AS inf,
                            '-infinity'::DATE AS minus_inf,
                            '5874897-12-31'::DATE AS mx,
                            '4713-01-01 BC'::DATE AS mn"
                );
                $tuple = $qr->tuple();
                $this->assertEquals(Date::fromParts(2016, 4, 17), $tuple['d'], $dateStyle);
                $this->assertEquals(Date::fromParts(-1987, 8, 17), $tuple['bc'], $dateStyle);
                $this->assertEquals(Date::infinity(), $tuple['inf'], $dateStyle);
                $this->assertEquals(Date::minusInfinity(), $tuple['minus_inf'], $dateStyle);
                $this->assertEquals(Date::fromParts(5874897, 12, 31), $tuple['mx'], $dateStyle);
                $this->assertEquals(Date::fromParts(-4713, 1, 1), $tuple['mn'], $dateStyle);
            }
        }
        finally {
            $conn->rollback();
        }
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

    public function testTimeResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT '0:00'::TIME AS midnight,
                    '07:59:60.123456'::TIME AS leap_sec,
                    '15:42:54'::TIME AS pm,
                    '24:00:00'::TIME AS next_midnight"
        );

        $tuple = $qr->tuple();
        $this->assertEquals(Time::fromPartsStrict(0, 0, 0), $tuple['midnight']);
        $this->assertEquals(Time::fromPartsStrict(8, 0, .123456), $tuple['leap_sec']);
        $this->assertEquals(Time::fromPartsStrict(15, 42, 54), $tuple['pm']);
        $this->assertEquals(Time::fromPartsStrict(24, 0, 0), $tuple['next_midnight']);
    }

    public function testTimeTzResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT '0:00+0000'::TIMETZ AS midnight,
                    '07:59:60.123456+13:30'::TIMETZ AS leap_sec,
                    '15:42:54-08:00'::TIMETZ AS pm,
                    '24:00:00+0600'::TIMETZ AS next_midnight"
        );

        $tuple = $qr->tuple();
        $this->assertEquals(TimeTz::fromPartsStrict(0, 0, 0, 0), $tuple['midnight']);
        $this->assertEquals(TimeTz::fromPartsStrict(8, 0, .123456, 48600), $tuple['leap_sec']);
        $this->assertEquals(TimeTz::fromPartsStrict(15, 42, 54, -28800), $tuple['pm']);
        $this->assertEquals(TimeTz::fromPartsStrict(24, 0, 0, 21600), $tuple['next_midnight']);
    }

    public function testTimestampResult()
    {
        $conn = $this->getIvoryConnection();
        $conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            foreach ($dateStyles as $dateStyle) {
                $conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                $qr = new QueryRelation($conn,
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
                $tuple = $qr->tuple();
                $this->assertEquals(Timestamp::fromParts(2016, 4, 17, 0, 0, 0), $tuple['midnight'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 5, 17, 8, 0, 0.123456), $tuple['leap_sec'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 6, 17, 15, 42, 54), $tuple['pm'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(2016, 7, 18, 0, 0, 0), $tuple['next_midnight'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(-1987, 8, 17, 13, 1, 2.5), $tuple['bc'], $dateStyle);
                $this->assertEquals(Timestamp::infinity(), $tuple['inf'], $dateStyle);
                $this->assertEquals(Timestamp::minusInfinity(), $tuple['minus_inf'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(294277, 1, 1, 0, 0, 0), $tuple['mx'], $dateStyle);
                $this->assertEquals(Timestamp::fromParts(-4713, 1, 1, 0, 0, 0), $tuple['mn'], $dateStyle);
            }
        }
        finally {
            $conn->rollback();
        }
    }

    public function testTimestampTzResult()
    {
        $conn = $this->getIvoryConnection();
        $conn->startTransaction();

        try {
            $dateStyles = ['ISO', 'German', 'SQL, DMY', 'SQL, MDY', 'Postgres, DMY', 'Postgres, MDY'];
            $timeZones = [
                'UTC' => [['+0000', 'UTC'], ['+0000', 'UTC']],
                'Europe/Prague' => [['+0100', 'CET'], ['+0200', 'CEST']],
            ];
            foreach ($dateStyles as $dateStyle) {
                $conn->getConfig()->setForTransaction(ConfigParam::DATE_STYLE, $dateStyle);
                foreach ($timeZones as $tz => list($tzStdSpec, $tzSmrSpec)) {
                    $tzStd = ($dateStyle == 'ISO' ? $tzStdSpec[0] : $tzStdSpec[1]);
                    $tzSmr = ($dateStyle == 'ISO' ? $tzSmrSpec[0] : $tzSmrSpec[1]);
                    $msg = "$dateStyle; $tz";

                    $conn->getConfig()->setForTransaction(ConfigParam::TIME_ZONE, $tz);
                    $qr = new QueryRelation($conn,
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
                    $tuple = $qr->tuple();
                    $this->errorInterruptMode();
                    if ($dateStyle == 'ISO' && $tz != 'UTC') {
                        $this->assertErrorsTriggered(2, "~Cutting '\\+00:57:44' to '\\+00:57'~", E_ALL, $msg);
                    }
                    $this->assertNoMoreErrors($msg);

                    $this->assertEquals(TimestampTz::fromParts(2016, 1, 17, 0, 0, 0, $tzStd), $tuple['midnight'], $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 6, 17, 15, 42, 54, $tzSmr), $tuple['pm'], $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 7, 18, 0, 0, 0, $tzSmr), $tuple['next_midnight'], $msg);
                    $this->assertEquals(TimestampTz::fromParts(2016, 5, 17, 8, 0, 0.123456, $tzSmr), $tuple['leap_sec'], $msg);
                    $this->assertEquals(TimestampTz::infinity(), $tuple['inf'], $msg);
                    $this->assertEquals(TimestampTz::minusInfinity(), $tuple['minus_inf'], $msg);
                    $this->assertEquals(TimestampTz::fromParts(294277, 1, 1, 0, 0, 0, $tzStd), $tuple['mx'], $msg);
                    if ($tz == 'UTC') {
                        /* Only testing very old years for UTC, as PHP does not support local mean time very well, and
                         * it is out of scope of Ivory to remedy that.
                         * See also:
                         * * https://en.wikipedia.org/wiki/Time_zone
                         * * https://en.wikipedia.org/wiki/Local_mean_time
                         * * http://dba.stackexchange.com/questions/127965/why-does-time-zone-have-such-a-crazy-offset-from-utc-on-year-0001-in-postgres
                         */
                        $this->assertEquals(TimestampTz::fromParts(-1987, 8, 17, 13, 1, 2.5, $tz), $tuple['bc'], $msg);
                        $this->assertEquals(TimestampTz::fromParts(-4713, 1, 1, 0, 0, 0, $tz), $tuple['mn'], $msg);
                    }
                }
            }
        }
        finally {
            $conn->rollback();
        }
    }

    public function testPgLsnResult()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn,
            "SELECT '16/B374D848'::pg_lsn,
                    '0/0'::pg_lsn,
                    'FFFFFFFF/FFFFFFFF'::pg_lsn"
        );

        $tuple = $qr->tuple();
        $this->assertEquals(PgLogSequenceNumber::fromString('16/B374D848'), $tuple[0]);
        $this->assertEquals(PgLogSequenceNumber::fromString('0/0'), $tuple[1]);
        $this->assertEquals(PgLogSequenceNumber::fromString('FFFFFFFF/FFFFFFFF'), $tuple[2]);
    }
}
