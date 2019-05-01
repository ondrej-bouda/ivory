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
        $this->assertTrue($set->contains('Google'));
        $this->assertTrue($set->contains('google'));
    }
}
