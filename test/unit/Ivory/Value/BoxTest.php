<?php
namespace Ivory\Value;

class BoxTest extends \PHPUnit\Framework\TestCase
{
    public function testGetArea()
    {
        $box = Box::fromOppositeCorners(Point::fromCoords(1, 3), Point::fromCoords(-3, 0));
        $this->assertEquals(12, $box->getArea(), '', 1e-9);
    }
}
