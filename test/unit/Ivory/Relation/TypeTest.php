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
use Ivory\Value\PgOperatorNameRef;
use Ivory\Value\PgOperatorRef;
use Ivory\Value\PgProcedureNameRef;
use Ivory\Value\PgProcedureRef;
use Ivory\Value\PgRelationRef;
use Ivory\Value\PgTextSearchConfigRef;
use Ivory\Value\PgTextSearchDictionaryRef;
use Ivory\Value\PgTypeRef;
use Ivory\Value\Point;
use Ivory\Value\TextSearchQuery;
use Ivory\Value\TextSearchVector;
use Ivory\Value\Time;
use Ivory\Value\TimeInterval;
use Ivory\Value\Timestamp;
use Ivory\Value\TimestampTz;
use Ivory\Value\TimeTz;
use Ivory\Value\TupleId;
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
        self::assertEquals(
            [
                'Metallica' => [
                    [
                        'id' => 2,
                        'name' => 'Black Album',
                        'year' => 1991,
                        'released' => Date::fromISOString('1991-08-12'),
                    ],
                    [
                        'id' => 3,
                        'name' => 'S & M',
                        'year' => 1999,
                        'released' => Date::fromISOString('1999-11-23'),
                    ],
                ],
                'The Piano Guys' => [
                    [
                        'id' => 1,
                        'name' => 'The Piano Guys',
                        'year' => 2012,
                        'released' => Date::fromISOString('2012-10-02'),
                    ],
                ],
                'Tommy Emmanuel' => [
                    [
                        'id' => 4,
                        'name' => 'Live One',
                        'year' => 2005,
                        'released' => Date::fromISOString('2005-01-01'),
                    ],
                ],
            ],
            $results
        );

        $album = $rel->value('albums', 2)[1];
        assert($album instanceof Composite);
        self::assertEquals(
            ['id' => 4, 'name' => 'Live One', 'year' => 2005, 'released' => Date::fromISOString('2005-01-01')],
            $album->toMap()
        );
        self::assertSame('Live One', $rel->value('albums', 2)[1]->name);
    }

    public function testAnonymousComposite()
    {
        $rel = $this->conn->query(
            "WITH a (k,l,m) AS (VALUES (1, 'a', true))
             SELECT a FROM a"
        );
        // there does not seem to be a way to get associative array, as well as the types of components
        self::assertSame(['1', 'a', 't'], $rel->value());
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

        self::assertSame(['Metallica', 'Robbie Williams', 'The Piano Guys', 'Tommy Emmanuel'], $map->getKeys());

        self::assertSame([1991, 1999], $map['Metallica']->toBounds('[]'));
        self::assertTrue($map['Robbie Williams']->isEmpty());
        self::assertSame([2012, 2012], $map['The Piano Guys']->toBounds('[]'));
        self::assertSame([2005, 2005], $map['Tommy Emmanuel']->toBounds('[]'));
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
        self::assertEquals(112, $areaSum, '', 1e-9);
    }

    public function testDate()
    {
        $rel = $this->conn->query(
            'SELECT name, released
             FROM album
             ORDER BY released, name'
        );

        $list = $rel->toArray();
        self::assertEquals(['name' => 'Black Album', 'released' => Date::fromParts(1991, 8, 12)], $list[0]);
        self::assertEquals(['name' => 'S & M', 'released' => Date::fromParts(1999, 11, 23)], $list[1]);
        self::assertEquals(['name' => 'Live One', 'released' => Date::fromParts(2005, 1, 1)], $list[2]);
        self::assertEquals(['name' => 'The Piano Guys', 'released' => Date::fromParts(2012, 10, 2)], $list[3]);

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
                self::assertEquals(Date::fromParts(2016, 4, 17), $tuple->d, $dateStyle);
                self::assertEquals(Date::fromParts(-1987, 8, 17), $tuple->bc, $dateStyle);
                self::assertEquals(Date::infinity(), $tuple->inf, $dateStyle);
                self::assertEquals(Date::minusInfinity(), $tuple->minus_inf, $dateStyle);
                self::assertEquals(Date::fromParts(5874897, 12, 31), $tuple->mx, $dateStyle);
                self::assertEquals(Date::fromParts(-4713, 1, 1), $tuple->mn, $dateStyle);
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

        self::assertSame(['Metallica', 'The Piano Guys', 'Tommy Emmanuel'], $rel->col('name')->toArray());

        self::assertEquals(
            [Date::fromParts(1991, 8, 12), Date::fromParts(1999, 11, 23)],
            $rel->value('activerng', 0)->toBounds('[]')
        );

        self::assertEquals(
            [Date::fromParts(2012, 10, 2), Date::fromParts(2012, 10, 2)],
            $rel->value('activerng', 1)->toBounds('[]')
        );

        self::assertEquals(
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
        self::assertEquals(Time::fromPartsStrict(0, 0, 0), $tuple->midnight);
        self::assertEquals(Time::fromPartsStrict(8, 0, .123456), $tuple->leap_sec);
        self::assertEquals(Time::fromPartsStrict(15, 42, 54), $tuple->pm);
        self::assertEquals(Time::fromPartsStrict(24, 0, 0), $tuple->next_midnight);
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
        self::assertEquals(TimeTz::fromPartsStrict(0, 0, 0, 0), $tuple->midnight);
        self::assertEquals(TimeTz::fromPartsStrict(8, 0, .123456, 48600), $tuple->leap_sec);
        self::assertEquals(TimeTz::fromPartsStrict(15, 42, 54, -28800), $tuple->pm);
        self::assertEquals(TimeTz::fromPartsStrict(24, 0, 0, 21600), $tuple->next_midnight);
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
                self::assertEquals(Timestamp::fromParts(2016, 4, 17, 0, 0, 0), $tuple->midnight, $dateStyle);
                self::assertEquals(Timestamp::fromParts(2016, 5, 17, 8, 0, 0.123456), $tuple->leap_sec, $dateStyle);
                self::assertEquals(Timestamp::fromParts(2016, 6, 17, 15, 42, 54), $tuple->pm, $dateStyle);
                self::assertEquals(Timestamp::fromParts(2016, 7, 18, 0, 0, 0), $tuple->next_midnight, $dateStyle);
                self::assertEquals(Timestamp::fromParts(-1987, 8, 17, 13, 1, 2.5), $tuple->bc, $dateStyle);
                self::assertEquals(Timestamp::infinity(), $tuple->inf, $dateStyle);
                self::assertEquals(Timestamp::minusInfinity(), $tuple->minus_inf, $dateStyle);
                self::assertEquals(Timestamp::fromParts(294276, 12, 31, 23, 59, 59.999999), $tuple->mx, $dateStyle);
                self::assertEquals(Timestamp::fromParts(-4713, 1, 1, 0, 0, 0), $tuple->mn, $dateStyle);
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

                    self::assertEquals(TimestampTz::fromParts(2016, 1, 17, 0, 0, 0, $tzStd), $tuple->midnight, $msg);
                    self::assertEquals(TimestampTz::fromParts(2016, 6, 17, 15, 42, 54, $tzSmr), $tuple->pm, $msg);
                    self::assertEquals(TimestampTz::fromParts(2016, 7, 18, 0, 0, 0, $tzSmr), $tuple->next_midnight,
                        $msg);
                    self::assertEquals(TimestampTz::fromParts(2016, 5, 17, 8, 0, 0.123456, $tzSmr), $tuple->leap_sec,
                        $msg);
                    self::assertEquals(TimestampTz::infinity(), $tuple->inf, $msg);
                    self::assertEquals(TimestampTz::minusInfinity(), $tuple->minus_inf, $msg);
                    self::assertEquals(TimestampTz::fromParts(-1987, 8, 17, 13, 1, 2.5, $lmtOffset), $tuple->bc,
                        $msg);
                    self::assertEquals(TimestampTz::fromParts(294276, 12, 31, 23, 59, 59.999999, $tzStd), $tuple->mx, $msg);
                    self::assertEquals(TimestampTz::fromParts(-4713, 1, 1, 0, 0, 0, $lmtOffset), $tuple->mn, $msg);
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
        self::assertSame(2, count($rel));
        self::assertEquals(Point::fromCoords(0, 0), $rel->tuple(0)->A);
        self::assertEquals(Point::fromCoords(0, 0), $rel->value('A', 0));
        self::assertEquals(
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
        self::assertEquals(PgLogSequenceNumber::fromString('16/B374D848'), $tuple[0]);
        self::assertEquals(PgLogSequenceNumber::fromString('0/0'), $tuple[1]);
        self::assertEquals(PgLogSequenceNumber::fromString('FFFFFFFF/FFFFFFFF'), $tuple[2]);
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
        self::assertEquals(TxIdSnapshot::fromParts(10, 20, [10, 14, 15]), $tuple[0]);
        self::assertEquals(TxIdSnapshot::fromParts(10, 20, [19]), $tuple[1]);
        self::assertEquals(TxIdSnapshot::fromParts(10, 20, []), $tuple[2]);
        self::assertEquals(TxIdSnapshot::fromParts(10, 10, []), $tuple[3]);
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

        self::assertEquals(
            TextSearchVector::fromSet(['a', 'and', 'ate', 'cat', 'fat', 'mat', 'on', 'rat', 'sat']),
            $tuple->list
        );

        self::assertEquals(
            TextSearchVector::fromSet(['    ', 'contains', 'lexeme', 'spaces', 'the']),
            $tuple->whitespace
        );

        self::assertEquals(
            TextSearchVector::fromSet(['a', 'contains', "Joe's", 'lexeme', 'quote', 'the']),
            $tuple->quotes
        );

        self::assertEquals(
            TextSearchVector::fromList(['a', 'fat', 'cat', 'sat', 'on', 'a', 'mat', 'and', 'ate', 'a', 'fat', 'rat']),
            $tuple->positioned
        );

        self::assertEquals(
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
        self::assertEquals(TextSearchQuery::fromString("!'fat' & ( 'rat':AB | 'cat' )"), $tuple->ops);
        self::assertEquals(TextSearchQuery::fromString("'super':*"), $tuple->star);
        self::assertEquals(TextSearchQuery::fromString("'Jo  e''s'''"), $tuple->quotes);
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
        self::assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::WEEK => 3, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6,
            ]),
            $tuple->iso
        );
        self::assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6,
            ]),
            $tuple->iso_alt
        );
        self::assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 2]),
            $tuple->sql_year_mon
        );
        self::assertEquals(
            TimeInterval::fromParts([
                TimeInterval::DAY => 3,
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34, TimeInterval::SECOND => 56,
            ]),
            $tuple->sql_day_time
        );
        self::assertEquals(
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
        self::assertSame([], $tuple->empty);
        self::assertSame(['k' => 'v'], $tuple->singleton);
        self::assertSame(['k' => null], $tuple->null_value);
        self::assertSame(['k' => '2'], $tuple->int_value);
        self::assertSame(['a' => 'X', 'b' => 'Y'], $tuple->multi_value);
    }

    public function testMacAddr()
    {
        $a6tuple = $this->conn->querySingleTuple(
            "SELECT %macaddr::macaddr AS a6,
                    %::macaddr AS a6auto",
            MacAddr::fromString('08:00:2b:01:02:03'),
            MacAddr::fromString('08:00:2b:01:02:03')
        );
        self::assertEquals(MacAddr::fromString('08:00:2b:01:02:03'), $a6tuple->a6);
        self::assertEquals(MacAddr::fromString('08:00:2b:01:02:03'), $a6tuple->a6auto);

        if ($this->conn->getConfig()->getServerMajorVersionNumber() >= 10) { // macaddr8 only since PostgreSQL 10
            $tuple = $this->conn->querySingleTuple(
                "SELECT macaddr8_set7bit(%macaddr) AS bit7set,
                        %macaddr8::macaddr8 AS a8,
                        %::macaddr8 AS a8auto",
                '08:00:2b:01:02:03',
                MacAddr8::fromString('08:00:2b:01:02:03:04:05'),
                MacAddr8::fromString('08:00:2b:01:02:03:04:05')
            );
            self::assertEquals(MacAddr8::fromString('0a:00:2b:ff:fe:01:02:03'), $tuple->bit7set);
            self::assertEquals(MacAddr8::fromString('08:00:2b:01:02:03:04:05'), $tuple->a8);
            self::assertEquals(MacAddr8::fromString('08:00:2b:01:02:03:04:05'), $tuple->a8auto);
        }
    }

    public function testIdent()
    {
        $typeDict = $this->conn->getTypeDictionary();

        $a = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 't');
        self::assertSame('SELECT * FROM t', $a->toSql($typeDict));

        $b = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 'T');
        self::assertSame('SELECT * FROM "T"', $b->toSql($typeDict));

        $c = SqlRelationDefinition::fromPattern('SELECT * FROM %ident', 'a.b');
        self::assertSame('SELECT * FROM "a.b"', $c->toSql($typeDict));

        $d = SqlRelationDefinition::fromPattern('SELECT * FROM %ident?', 't');
        self::assertSame('SELECT * FROM t', $d->toSql($typeDict));

        $e = SqlRelationDefinition::fromPattern('SELECT * FROM %ident?', 'T');
        self::assertSame('SELECT * FROM "T"', $e->toSql($typeDict));

        $f = SqlRelationDefinition::fromPattern('SELECT * FROM %ident?', 'a.b');
        self::assertSame('SELECT * FROM "a.b"', $f->toSql($typeDict));
    }

    public function testExceptionHintsOnUndefinedType()
    {
        try {
            $this->conn->querySingleValue(
                'SELECT 1 FROM %ident.pg_class', // an obvious error - %{ident}.pg_class should have been used instead
                'pg_catalog'
            );
        } catch (UndefinedTypeException $e) {
            self::assertStringContainsStringIgnoringCase('did you mean', $e->getMessage());
            self::assertContains('%{ident}.pg_class', $e->getMessage());
        }

        $res = $this->conn->querySingleValue('SELECT 1 FROM %{ident}.pg_class LIMIT 1', 'pg_catalog');
        self::assertSame(1, $res);
    }

    public function testSerializeObjectIdentifierTypes()
    {
        $typeDict = $this->conn->getTypeDictionary();

        $oidRel = SqlRelationDefinition::fromPattern('SELECT %oid, %oid', 987, null);
        self::assertSame(
            'SELECT 987::pg_catalog.oid, NULL::pg_catalog.oid',
            $oidRel->toSql($typeDict)
        );

        $regProcRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regproc',
            PgProcedureNameRef::fromQualifiedName('pg_catalog', 'pg_typeof'),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.regproc 'pg_catalog.pg_typeof', NULL::pg_catalog.regproc",
            $regProcRel->toSql($typeDict)
        );

        $regProcedureRel = SqlRelationDefinition::fromPattern(
            'SELECT %regprocedure, %, %regprocedure',
            PgProcedureRef::fromUnqualifiedName('abs', PgTypeRef::fromUnqualifiedName('integer')),
            PgProcedureRef::fromUnqualifiedName('abbrev', PgTypeRef::fromQualifiedName('pg_catalog', 'cidr')),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.regprocedure 'abs(integer)', pg_catalog.regprocedure 'abbrev(pg_catalog.cidr)', " .
            "NULL::pg_catalog.regprocedure",
            $regProcedureRel->toSql($typeDict)
        );

        $regOperRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regoper',
            PgOperatorNameRef::fromUnqualifiedName('!'),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.regoper '\"!\"', NULL::pg_catalog.regoper",
            $regOperRel->toSql($typeDict)
        );

        $regOperatorRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regoperator',
            PgOperatorRef::fromUnqualifiedName(
                '+',
                PgTypeRef::fromUnqualifiedName('integer'),
                PgTypeRef::fromUnqualifiedName('integer')
            ),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.regoperator '\"+\"(integer, integer)', NULL::pg_catalog.regoperator",
            $regOperatorRel->toSql($typeDict)
        );

        $regClassRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regclass, %regclass',
            PgRelationRef::fromUnqualifiedName('pg_class'),
            PgRelationRef::fromQualifiedName('some."schema"', '"tbl"'),
            null
        );
        self::assertSame(
            <<<'SQL'
SELECT pg_catalog.regclass 'pg_class', pg_catalog.regclass '"some.""schema"""."""tbl"""', NULL::pg_catalog.regclass
SQL
,
            $regClassRel->toSql($typeDict)
        );

        $regTypeRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regtype',
            PgTypeRef::fromQualifiedName('pg_catalog', 'bool'),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.regtype 'pg_catalog.bool', NULL::pg_catalog.regtype",
            $regTypeRel->toSql($typeDict)
        );

        // NOTE: regrole and regnamespace have been introduced in PostgreSQL 9.5
        if ($this->conn->getConfig()->getServerVersionNumber() >= 90500) {
            $regRoleRel = SqlRelationDefinition::fromPattern('SELECT %regrole, %regrole', 'postgres', null);
            self::assertSame(
                "SELECT pg_catalog.regrole 'postgres', NULL::pg_catalog.regrole",
                $regRoleRel->toSql($typeDict)
            );

            $regNamespaceRel = SqlRelationDefinition::fromPattern(
                'SELECT %regnamespace, %regnamespace',
                'some."schema"',
                null
            );
            self::assertSame(
                <<<'SQL'
SELECT pg_catalog.regnamespace '"some.""schema"""', NULL::pg_catalog.regnamespace
SQL
                ,
                $regNamespaceRel->toSql($typeDict)
            );
        }

        $regConfigRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regconfig',
            PgTextSearchConfigRef::fromQualifiedName('some."schema"', 'fts_config'),
            null
        );
        self::assertSame(
            <<<'SQL'
SELECT pg_catalog.regconfig '"some.""schema""".fts_config', NULL::pg_catalog.regconfig
SQL
            ,
            $regConfigRel->toSql($typeDict)
        );

        $regDictionaryRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %regdictionary',
            PgTextSearchDictionaryRef::fromQualifiedName('some."schema"', 'fts_dict'),
            null
        );
        self::assertSame(
            <<<'SQL'
SELECT pg_catalog.regdictionary '"some.""schema""".fts_dict', NULL::pg_catalog.regdictionary
SQL
            ,
            $regDictionaryRel->toSql($typeDict)
        );

        $tupleIdRel = SqlRelationDefinition::fromPattern(
            'SELECT %, %tid',
            TupleId::fromCoordinates(4013, 824928),
            null
        );
        self::assertSame(
            "SELECT pg_catalog.tid '(4013,824928)', NULL::pg_catalog.tid",
            $tupleIdRel->toSql($typeDict)
        );

        $otherRel = SqlRelationDefinition::fromPattern('SELECT %xid, %cid', 123, 456);
        self::assertSame(
            "SELECT pg_catalog.xid '123', pg_catalog.cid '456'",
            $otherRel->toSql($typeDict)
        );
    }

    public function testParseObjectIdentifierTypes()
    {
        $tx = $this->conn->startTransaction();
        try {
            $this->conn->command('CREATE SCHEMA "some.""schema"""');
            $this->conn->command('CREATE TABLE "some.""schema"""."""tbl""" ()');
            $this->conn->command('CREATE TEXT SEARCH CONFIGURATION "some.""schema""".fts_config (PARSER = default)');
            $this->conn->command('CREATE TEXT SEARCH DICTIONARY "some.""schema""".fts_dict (TEMPLATE = simple)');

            $tuple = $this->conn->querySingleTuple(
                <<<'SQL'
                SELECT
                    987::oid AS oid,
                    'pg_catalog.pg_typeof'::regproc AS regproc,
                    'pg_catalog.abs(integer)'::regprocedure AS regprocedure,
                    '!'::regoper AS regoper,
                    '+(integer, integer)'::regoperator AS regoperator,
                    'pg_catalog.pg_class'::regclass AS regclass,
                    '"some.""schema"""."""tbl"""'::regclass AS regclass_tbl,
                    pg_catalog.pg_typeof(FALSE) AS regtype,
                    '"some.""schema""".fts_config'::regconfig AS regconfig,
                    '"some.""schema""".fts_dict'::regdictionary AS regdictionary,
                    '123'::xid AS xid,
                    '456'::cid AS cid,
                    '(7,8)'::tid AS tid
SQL
            );
            self::assertSame(987, $tuple->oid);
            self::assertEquals(PgProcedureNameRef::fromUnqualifiedName('pg_typeof'), $tuple->regproc);
            self::assertEquals(
                PgProcedureRef::fromUnqualifiedName('abs', PgTypeRef::fromUnqualifiedName('integer')),
                $tuple->regprocedure
            );
            self::assertEquals(PgOperatorNameRef::fromUnqualifiedName('!'), $tuple->regoper);
            self::assertEquals(
                PgOperatorRef::fromUnqualifiedName(
                    '+',
                    PgTypeRef::fromUnqualifiedName('integer'),
                    PgTypeRef::fromUnqualifiedName('integer')
                ),
                $tuple->regoperator
            );
            self::assertEquals(PgRelationRef::fromUnqualifiedName('pg_class'), $tuple->regclass);
            self::assertEquals(PgRelationRef::fromQualifiedName('some."schema"', '"tbl"'), $tuple->regclass_tbl);
            self::assertEquals(PgTypeRef::fromUnqualifiedName('boolean'), $tuple->regtype);
            self::assertEquals(
                PgTextSearchConfigRef::fromQualifiedName('some."schema"', 'fts_config'),
                $tuple->regconfig
            );
            self::assertEquals(
                PgTextSearchDictionaryRef::fromQualifiedName('some."schema"', 'fts_dict'),
                $tuple->regdictionary
            );
            self::assertSame(123, $tuple->xid);
            self::assertSame(456, $tuple->cid);
            self::assertEquals(TupleId::fromCoordinates(7, 8), $tuple->tid);

            // NOTE: regrole and regnamespace have been introduced in PostgreSQL 9.5
            if ($this->conn->getConfig()->getServerVersionNumber() >= 90500) {
                $tuple = $this->conn->querySingleTuple(
                    <<<'SQL'
                    SELECT
                        'postgres'::regrole AS regrole,
                        'pg_catalog'::regnamespace AS regnamespace,
                        '"some.""schema"""'::regnamespace AS regnamespace_some_schema
SQL
                );
                self::assertSame('postgres', $tuple->regrole);
                self::assertSame('pg_catalog', $tuple->regnamespace);
                self::assertSame('some."schema"', $tuple->regnamespace_some_schema);
            }
        } finally {
            $tx->rollback();
        }
    }

    public function testDoublePrecision()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT
                 pg_catalog.pg_typeof(%{double precision}?)::TEXT AS double_implicit,
                 pg_catalog.pg_typeof(%{double precision})::TEXT AS double_explicit',
            1.2,
            1.2
        );
        self::assertSame('numeric', $tuple->double_implicit);
        self::assertSame('double precision', $tuple->double_explicit);
    }

}
