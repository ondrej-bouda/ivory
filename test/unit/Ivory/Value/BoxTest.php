<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class BoxTest extends TestCase
{
    public function testGetArea()
    {
        $box = Box::fromOppositeCorners(Point::fromCoords(1, 3), Point::fromCoords(-3, 0));
        self::assertEquals(12, $box->getArea(), '', 1e-9);
    }
}
