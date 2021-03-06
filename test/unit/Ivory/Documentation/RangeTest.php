<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\IvoryTestCase;
use Ivory\Value\Range;
use Ivory\Value\Timestamp;

/**
 * This test shows ranges in Ivory.
 *
 * @see https://www.postgresql.org/docs/11/rangetypes.html#RANGETYPES-EXAMPLES
 */
class RangeTest extends IvoryTestCase
{
    public function testOperations()
    {
        // Containment
        self::assertFalse(
            Range::fromBounds(10, 20)->containsElement(3)
        );

        // Overlaps
        self::assertTrue(
            Range::fromBounds(11.1, 22.2)->overlaps(Range::fromBounds(20.0, 30.0))
        );

        // Extract the upper bound
        self::assertSame(25, Range::fromBounds(15, 25)->getUpper());

        // Compute the intersection
        self::assertEquals(
            Range::fromBounds(15, 20),
            Range::fromBounds(10, 20)->intersect(Range::fromBounds(15, 25))
        );

        // Is the range empty?
        self::assertFalse(Range::fromBounds(1, 5)->isEmpty());
    }

    public function testQueries()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE TABLE reservation (room int, during tsrange)');
            $conn->command(
                'INSERT INTO reservation (room, during) VALUES (%i, %tsrange)',
                1108,
                Range::fromBounds(
                    Timestamp::fromParts(2010, 1, 1, 14, 30, 0),
                    Timestamp::fromParts(2010, 1, 1, 15, 30, 0)
                )
            );

            $resRange = $conn->querySingleValue('SELECT during FROM reservation WHERE room = %i', 1108);
            $dayRange = Range::fromBounds(
                Timestamp::fromParts(2010, 1, 1, 0, 0, 0),
                Timestamp::fromParts(2010, 1, 2, 0, 0, 0)
            );
            self::assertTrue($dayRange->containsRange($resRange));
        } finally {
            $tx->rollback();
        }
    }

    public function testBounds()
    {
        $range = Range::fromBounds(10, 20, '[]');
        self::assertSame(10, $range->getLower());
        self::assertSame(20, $range->getUpper());

        self::assertSame([10, 21], $range->toBounds('[)'));
        self::assertSame([9, 20], $range->toBounds('(]'));
    }

    public function testDiscreteRanges()
    {
        self::assertTrue(Range::fromBounds(3, 4, '[)')->isSinglePoint());
        self::assertTrue(Range::fromBounds(3, 4, '()')->isEmpty());
    }
}
