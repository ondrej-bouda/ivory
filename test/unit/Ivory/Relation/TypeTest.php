<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;
use Ivory\IvoryTestCase;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Value\Box;
use Ivory\Value\Composite;
use Ivory\Value\Date;
use Ivory\Value\MacAddr;
use Ivory\Value\MacAddr8;
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

class TypeTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }

    public function testComposite()
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
                assert($album instanceof Composite);
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

        $album = $rel->value('albums', 2)[1];
        assert($album instanceof Composite);
        $this->assertEquals(
            ['id' => 4, 'name' => 'Live One', 'year' => 2005, 'released' => Date::fromISOString('2005-01-01')],
            $album->toMap()
        );
        $this->assertSame('Live One', $rel->value('albums', 2)[1]->name);
    }

    public function testAnonymousComposite()
    {
        $rel = $this->conn->query(
            "WITH a (k,l,m) AS (VALUES (1, 'a', true))
             SELECT a FROM a"
        );
        // there does not seem to be a way to get associative array, as well as the types of components
        $this->assertSame(['1', 'a', 't'], $rel->value());
    }

    public function testRange()
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

    public function testBoxArray()
    {
        $rel = $this->conn->query(
            "SELECT ARRAY[box(point(1,3),point(-3,5)), box(point(0,0),point(10,10)), box(point(1,2),point(3,4))]"
        );
        $areaSum = 0;
        foreach ($rel->value() as $box) {
            assert($box instanceof Box);
            $areaSum += $box->getArea();
        }
        $this->assertEquals(112, $areaSum, '', 1e-9);
    }

    public function testDate()
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

        $tx = $this->conn->startTransaction();

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
            $tx->rollback();
        }
    }

    public function testDateRange()
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

    public function testTime()
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

    public function testTimeTz()
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

    public function testTimestamp()
    {
        $tx = $this->conn->startTransaction();

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
                            '294276-12-31 23:59:59.999999'::TIMESTAMP AS mx,
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
                $this->assertEquals(Timestamp::fromParts(294276, 12, 31, 23, 59, 59.999999), $tuple->mx, $dateStyle);
                $this->assertEquals(Timestamp::fromParts(-4713, 1, 1, 0, 0, 0), $tuple->mn, $dateStyle);
            }
        } finally {
            $tx->rollback();
        }
    }

    public function testTimestampTz()
    {
        $tx = $this->conn->startTransaction();

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
                                '294276-12-31 23:59:59.999999'::TIMESTAMPTZ AS mx,
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
                    $this->assertEquals(TimestampTz::fromParts(294276, 12, 31, 23, 59, 59.999999, $tzStd), $tuple->mx, $msg);
                    $this->assertEquals(TimestampTz::fromParts(-4713, 1, 1, 0, 0, 0, $lmtOffset), $tuple->mn, $msg);
                }
            }
        } finally {
            $tx->rollback();
        }
    }

    public function testPoint()
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

    public function testPgLsn()
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

    public function testTxidSnapshot()
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

    public function testTsVector()
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

    public function testTsQuery()
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

    public function testInterval()
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

    public function testHstore()
    {
        $tuple = $this->conn->querySingleTuple(
            "SELECT hstore(ROW()) AS empty,
                    hstore('k', 'v') AS singleton,
                    hstore('k', NULL) AS null_value,
                    hstore('k', '2') AS int_value,
                    hstore(ARRAY['a', 'X', 'b', 'Y']) AS multi_value"
        );
        $this->assertSame([], $tuple->empty);
        $this->assertSame(['k' => 'v'], $tuple->singleton);
        $this->assertSame(['k' => null], $tuple->null_value);
        $this->assertSame(['k' => '2'], $tuple->int_value);
        $this->assertSame(['a' => 'X', 'b' => 'Y'], $tuple->multi_value);
    }

    public function testMacAddr()
    {
        $a6tuple = $this->conn->querySingleTuple(
            "SELECT %macaddr::macaddr AS a6,
                    %::macaddr AS a6auto",
            MacAddr::fromString('08:00:2b:01:02:03'),
            MacAddr::fromString('08:00:2b:01:02:03')
        );
        $this->assertEquals(MacAddr::fromString('08:00:2b:01:02:03'), $a6tuple->a6);
        $this->assertEquals(MacAddr::fromString('08:00:2b:01:02:03'), $a6tuple->a6auto);

        if ($this->conn->getConfig()->getServerMajorVersionNumber() >= 10) { // macaddr8 only since PostgreSQL 10
            $tuple = $this->conn->querySingleTuple(
                "SELECT macaddr8_set7bit(%macaddr) AS bit7set,
                        %macaddr8::macaddr8 AS a8,
                        %::macaddr8 AS a8auto",
                '08:00:2b:01:02:03',
                MacAddr8::fromString('08:00:2b:01:02:03:04:05'),
                MacAddr8::fromString('08:00:2b:01:02:03:04:05')
            );
            $this->assertEquals(MacAddr8::fromString('0a:00:2b:ff:fe:01:02:03'), $tuple->bit7set);
            $this->assertEquals(MacAddr8::fromString('08:00:2b:01:02:03:04:05'), $tuple->a8);
            $this->assertEquals(MacAddr8::fromString('08:00:2b:01:02:03:04:05'), $tuple->a8auto);
        }
    }

    public function testIdent()
    {
        $typeDict = $this->conn->getTypeDictionary();

        $a = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 't');
        $this->assertSame('SELECT * FROM t', $a->toSql($typeDict));

        $b = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 'T');
        $this->assertSame('SELECT * FROM "T"', $b->toSql($typeDict));

        $c = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 'a.b');
        $this->assertSame('SELECT * FROM "a.b"', $c->toSql($typeDict));

        $d = SqlRelationDefinition::fromPattern('SELECT * FROM %ident!', 't');
        $this->assertSame('SELECT * FROM "t"', $d->toSql($typeDict));

        $e = SqlRelationDefinition::fromPattern('SELECT * FROM %ident!', 'T');
        $this->assertSame('SELECT * FROM "T"', $e->toSql($typeDict));

        $f = SqlRelationDefinition::fromPattern('SELECT * FROM %ident!', 'a.b');
        $this->assertSame('SELECT * FROM "a.b"', $f->toSql($typeDict));
    }

    public function testExceptionHintsOnUndefinedType()
    {
        try {
            $this->conn->querySingleValue(
                'SELECT 1 FROM %ident.pg_class', // an obvious error - %{ident}.pg_class should have been used instead
                'pg_catalog'
            );
        } catch (UndefinedTypeException $e) {
            $this->assertStringContainsStringIgnoringCase('did you mean', $e->getMessage());
            $this->assertContains('%{ident}.pg_class', $e->getMessage());
        }

        $res = $this->conn->querySingleValue('SELECT 1 FROM %{ident}.pg_class LIMIT 1', 'pg_catalog');
        $this->assertSame(1, $res);
    }

//    public function testObjectIdentifierTypes()
//    {
//        $tuple = $this->conn->querySingleTuple(
//            <<<'SQL'
//            SELECT
//                987::oid,
//                'pg_catalog.pg_typeof'::regproc,
//                'pg_catalog.pg_typeof("any")'::regprocedure,
//                '!'::regoper,
//                '+(integer, integer)'::regoperator,
//                'pg_catalog.pg_class'::regclass,
//                pg_catalog.pg_typeof(1.0),
//                'postgres'::regrole,
//                'pg_catalog'::regnamespace,
//                'english'::regconfig,
//                'simple'::regdictionary,
//                '123'::xid,
//                '456'::cid,
//                '(7,8)'::tid
//SQL
//        );
//        // TODO: test the result
//    }

    public function testDoublePrecision()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT
                 pg_catalog.pg_typeof(%{double precision})::TEXT AS double,
                 pg_catalog.pg_typeof(%{double precision}!)::TEXT AS double_explicit',
            1.2,
            1.2
        );
        $this->assertSame('numeric', $tuple->double);
        $this->assertSame('double precision', $tuple->double_explicit);
    }

}
