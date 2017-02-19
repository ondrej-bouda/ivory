<?php
namespace Ivory\Data\Set;

class CustomSetTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $set = new CustomSet(function ($val) { return strtolower($val); });
        $set->add('Google');
        $this->assertTrue($set->contains('Google'));
        $this->assertTrue($set->contains('google'));
    }
}
