<?php
declare(strict_types=1);

namespace Ivory\Value;

class LineSegmentTest extends \PHPUnit\Framework\TestCase
{
    public function testGetLength()
    {
        $seg = LineSegment::fromEndpoints(Point::fromCoords(1, 3), Point::fromCoords(-3, 0));
        $this->assertEquals(5, $seg->getLength(), '', 1e-9);
    }
}
