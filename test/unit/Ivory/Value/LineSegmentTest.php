<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class LineSegmentTest extends TestCase
{
    public function testGetLength()
    {
        $seg = LineSegment::fromEndpoints(Point::fromCoords(1, 3), Point::fromCoords(-3, 0));
        $this->assertEquals(5, $seg->getLength(), '', 1e-9);
    }
}
