<?php
declare(strict_types=1);
namespace Ivory\Data\Set;

use PHPUnit\Framework\TestCase;

class CustomSetTest extends TestCase
{
    public function testBasic()
    {
        $set = new CustomSet(function ($val) { return strtolower($val); });
        $set->add('Google');
        self::assertTrue($set->contains('Google'));
        self::assertTrue($set->contains('google'));
    }
}
