<?php
namespace Ivory\Value;

class PolygonTest extends \PHPUnit\Framework\TestCase
{
    public function testGetArea()
    {
        $polygon = Polygon::fromPoints([ // points declared clockwise
            [1, 4],
            [10, 4],
            [11, 8],
            [2, 8],
        ]);
        $this->assertEquals(36, $polygon->getArea(), '', 1e-12);

        $polygon = Polygon::fromPoints([ // points declared counterclockwise
            [1, 1],
            [1, 5],
            [4, 3],
        ]);
        $this->assertEquals(6, $polygon->getArea());
    }
}
