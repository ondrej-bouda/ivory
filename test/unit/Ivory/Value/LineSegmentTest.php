<?php
namespace Ivory\Value;

class LineSegmentTest extends \PHPUnit_Framework_TestCase
{
    public function testGetLength()
    {
        $seg = LineSegment::fromEndpoints(Point::fromCoords(1, 3), Point::fromCoords(-3, 0));
        $this->assertEquals(5, $seg->getLength(), '', 1e-9);
    }
}
