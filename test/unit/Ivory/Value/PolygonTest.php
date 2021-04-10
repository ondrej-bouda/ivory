<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class PolygonTest extends TestCase
{
    public function testGetArea()
    {
        $polygon = Polygon::fromPoints([ // points declared clockwise
            [1, 4],
            [10, 4],
            [11, 8],
            [2, 8],
        ]);
        self::assertEqualsWithDelta(36, $polygon->getArea(), 1e-12);

        $polygon = Polygon::fromPoints([ // points declared counterclockwise
            [1, 1],
            [1, 5],
            [4, 3],
        ]);
        self::assertEquals(6, $polygon->getArea());
    }
}
