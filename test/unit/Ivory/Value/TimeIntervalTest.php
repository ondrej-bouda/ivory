<?php
declare(strict_types=1);
namespace Ivory\Value;

class TimeIntervalTest extends \PHPUnit\Framework\TestCase
{
    public function testFromParts()
    {
        $this->assertSame(
            [TimeInterval::SECOND => 0],
            TimeInterval::fromParts([])->toParts()
        );
        $this->assertSame(
            [TimeInterval::SECOND => 0],
            TimeInterval::fromParts([TimeInterval::YEAR => 0])->toParts()
        );
        $this->assertSame(
            [TimeInterval::SECOND => 0],
            TimeInterval::fromParts([TimeInterval::DAY => 14, TimeInterval::WEEK => -2])->toParts()
        );
        $this->assertSame(
            [TimeInterval::HOUR => 24],
            TimeInterval::fromParts([TimeInterval::WEEK => 1, TimeInterval::DAY => -7, TimeInterval::HOUR => 24])
                ->toParts()
        );
        $this->assertSame(
            [TimeInterval::YEAR => 1999, TimeInterval::DAY => 700],
            TimeInterval::fromParts([TimeInterval::MILLENNIUM => 2, TimeInterval::YEAR => -1, TimeInterval::DAY => 700])
                ->toParts()
        );
        $this->assertSame(
            [TimeInterval::HOUR => 12, TimeInterval::SECOND => .5],
            TimeInterval::fromParts([TimeInterval::DAY => .5, TimeInterval::SECOND => .5])->toParts()
        );
        $this->assertSame(
            [TimeInterval::YEAR => 2, TimeInterval::DAY => 10, TimeInterval::HOUR => 12],
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 12.35])->toParts()
        );

        $parts = TimeInterval::fromParts([TimeInterval::WEEK => 3.987])->toParts();
        $this->assertSame(
            [TimeInterval::DAY => 27, TimeInterval::HOUR => 21, TimeInterval::MINUTE => 48],
            array_slice($parts, 0, -1)
        );
        $this->assertEquals(57.6, $parts[TimeInterval::SECOND], '', 1e-9);
    }

    public function testFromString()
    {
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::SECOND => 0]),
            TimeInterval::fromString('P0Y'),
            'empty'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => 9]),
            TimeInterval::fromString('P9D'),
            'nine days'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => 9]),
            TimeInterval::fromString('P1W2D'),
            'nine days using week'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::WEEK => 3, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6.789012,
            ]),
            TimeInterval::fromString('P1Y2M3W3DT4H5M6.789012S'),
            'iso full'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => -1, TimeInterval::MONTH => -2, TimeInterval::WEEK => -3, TimeInterval::DAY => -3,
                TimeInterval::HOUR => -4, TimeInterval::MINUTE => -5, TimeInterval::SECOND => -6.789012,
            ]),
            TimeInterval::fromString('P-1Y-2M-3W-3DT-4H-5M-6.789012S'),
            'iso_neg'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6.789012,
            ]),
            TimeInterval::fromString('P0001-02-03T04:05:06.789012'),
            'iso alt datetime'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::DAY => 3]),
            TimeInterval::fromString('P0001-02-03'),
            'iso alt date'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::HOUR => 4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6]),
            TimeInterval::fromString('PT04:05:06'),
            'iso alt time'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => -1, TimeInterval::MONTH => -2, TimeInterval::DAY => -3,
                TimeInterval::HOUR => -4, TimeInterval::MINUTE => -5, TimeInterval::SECOND => -6.789012,
            ]),
            TimeInterval::fromString('P-0001--02--03T-04:-05:-06.789012'),
            'iso alt neg'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => -1, TimeInterval::MONTH => -2, TimeInterval::DAY => 3,
                TimeInterval::HOUR => -4, TimeInterval::MINUTE => 5, TimeInterval::SECOND => 6,
            ]),
            TimeInterval::fromString('P-0001--02-03T-04:05:06'),
            'iso alt neg some'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 2]),
            TimeInterval::fromString('1-2'),
            'sql year mon'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::SECOND => 3]),
            TimeInterval::fromString('1-2 3'),
            'sql year mon sec'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2,
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34,
            ]),
            TimeInterval::fromString('1-2 12:34'),
            'sql year mon time'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 2, TimeInterval::DAY => 3,
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34,
            ]),
            TimeInterval::fromString('1-2 3 12:34'),
            'sql datetime'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::DAY => 3,
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34, TimeInterval::SECOND => 56.78
            ]),
            TimeInterval::fromString('3 12:34:56.78'),
            'sql day time'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::HOUR => 12, TimeInterval::MINUTE => 34, TimeInterval::SECOND => 56.78
            ]),
            TimeInterval::fromString('12:34:56.78'),
            'sql time'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::SECOND => 42]),
            TimeInterval::fromString('42'),
            'sql sec'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => -1, TimeInterval::MONTH => -2, TimeInterval::DAY => -3,
                TimeInterval::HOUR => -12, TimeInterval::MINUTE => -34, TimeInterval::SECOND => -56.78,
            ]),
            TimeInterval::fromString('-1-2 -3 -12:34:56.78'),
            'sql neg'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 1]),
            TimeInterval::fromString('@ 1 year'),
            'pg verbose'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::YEAR => 2, TimeInterval::MONTH => 3]),
            TimeInterval::fromString('2 years 3 mon'),
            'pg year mon'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => -4]),
            TimeInterval::fromString('4 days ago'),
            'pg ago'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::MILLENNIUM => 1, TimeInterval::CENTURY => 2, TimeInterval::DECADE => 3,
                TimeInterval::YEAR => 4, TimeInterval::MONTH => 5, TimeInterval::WEEK => 6, TimeInterval::DAY => 7,
                TimeInterval::HOUR => 8, TimeInterval::MINUTE => 9, TimeInterval::SECOND => 10,
                TimeInterval::MILLISECOND => 11, TimeInterval::MICROSECOND => 12,
            ]),
            TimeInterval::fromString(
                '1 millennium 2 century 3 decade 4 year 5 month 6 week 7 day 8 hour 9 minute 10 second ' .
                '11 millisecond 12 microsecond'
            ),
            'pg full'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::MILLENNIUM => 1, TimeInterval::CENTURY => 2, TimeInterval::DECADE => 3,
                TimeInterval::YEAR => 4, TimeInterval::MONTH => 5, TimeInterval::WEEK => 6, TimeInterval::DAY => 7,
                TimeInterval::HOUR => 8, TimeInterval::MINUTE => 9, TimeInterval::SECOND => 10,
                TimeInterval::MILLISECOND => 11, TimeInterval::MICROSECOND => 12,
            ]),
            TimeInterval::fromString(
                '1 millenniums 2 centuries 3 decades 4 years 5 months 6 weeks 7 days 8 hours 9 minutes 10 seconds ' .
                '11 milliseconds 12 microseconds'
            ),
            'pg full plural'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::MILLENNIUM => 1, TimeInterval::CENTURY => 2, TimeInterval::DECADE => 3,
                TimeInterval::YEAR => 4, TimeInterval::MONTH => 5, TimeInterval::WEEK => 6, TimeInterval::DAY => 7,
                TimeInterval::HOUR => 8, TimeInterval::MINUTE => 9, TimeInterval::SECOND => 10,
                TimeInterval::MILLISECOND => 11, TimeInterval::MICROSECOND => 12,
            ]),
            TimeInterval::fromString(
                '1 mil 2 cent 3 dec 4 y 5 mon 6 w 7 d 8 h 9 min 10 sec 11 millisecond 12 microsecond'
            ),
            'pg abbr'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::MILLENNIUM => 1, TimeInterval::CENTURY => 2, TimeInterval::DECADE => 3,
                TimeInterval::YEAR => 4, TimeInterval::MONTH => 5, TimeInterval::WEEK => 6, TimeInterval::DAY => 7,
                TimeInterval::HOUR => 8, TimeInterval::MINUTE => 9, TimeInterval::SECOND => 10,
                TimeInterval::MILLISECOND => 11, TimeInterval::MICROSECOND => 12,
            ]),
            TimeInterval::fromString(
                '1 mils 2 centuries 3 decs 4 years 5 mons 6 weeks 7 days 8 hours 9 mins 10 secs 11 millisecond ' .
                '12 microsecond'
            ),
            'pg_abbr_plural'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::MILLENNIUM => 1, TimeInterval::CENTURY => 2, TimeInterval::DECADE => 3,
                TimeInterval::YEAR => 4, TimeInterval::MONTH => 5, TimeInterval::WEEK => 6, TimeInterval::DAY => 7,
                TimeInterval::HOUR => 8, TimeInterval::MINUTE => 9, TimeInterval::SECOND => 10,
                TimeInterval::MILLISECOND => 11, TimeInterval::MICROSECOND => 12,
            ]),
            TimeInterval::fromString('1 mil 2 cent 3 dec 4 y 5 mon 6 w 7 d 8 h 9 m 10 s 11 millisecond 12 microsecond'),
            'pg abbr2'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::DAY => 1, TimeInterval::WEEK => 4, TimeInterval::MONTH => 8,
                TimeInterval::MICROSECOND => 1
            ]),
            TimeInterval::fromString('1 day 4 weeks 1 microsecond 8 months'),
            'pg unsorted units'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => 1, TimeInterval::HOUR => -3, TimeInterval::SECOND => 6]),
            TimeInterval::fromString('-1 day 3 hours -6 seconds ago'),
            'pg mixed'
        );
        $this->assertEquals(
            TimeInterval::fromParts([TimeInterval::DAY => 1, TimeInterval::MINUTE => 14]),
            TimeInterval::fromString('1 day 00:14:00'),
            'pg with ISO time'
        );
        $this->assertEquals(
            TimeInterval::fromParts([
                TimeInterval::YEAR => 1, TimeInterval::MONTH => 6, TimeInterval::DAY => 4,
                TimeInterval::HOUR => 4, TimeInterval::MINUTE => 48,
            ]),
            TimeInterval::fromString('P1.5Y0.6W'),
            'frac'
        );
    }

    public function testToIsoString()
    {
        $this->assertSame(
            'P1Y5DT59M',
            TimeInterval::fromParts([
                TimeInterval::MONTH => 12, TimeInterval::DAY => 5, TimeInterval::HOUR => 1, TimeInterval::SECOND => -60
            ])->toIsoString()
        );

        $this->assertSame(
            'PT0S',
            TimeInterval::fromParts([TimeInterval::CENTURY => 1.5, TimeInterval::YEAR => -150])->toIsoString()
        );
    }
}
