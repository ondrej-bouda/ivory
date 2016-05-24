<?php
namespace Ivory\Data\Set;

class DictionarySetTest extends \PHPUnit_Framework_TestCase
{
    public function testBasic()
    {
        $set = new DictionarySet();
        $this->assertSame(0, $set->count());
        $this->assertFalse($set->contains(3));

        $set->add(1);
        $set->add(3);
        $this->assertSame(2, $set->count());
        $this->assertTrue($set->contains(3));
        $this->assertFalse($set->contains(2));

        $set->add(3);
        $this->assertSame(2, $set->count());
        $this->assertTrue($set->contains(3));

        $set->remove(3);
        $this->assertSame(1, $set->count());
        $this->assertFalse($set->contains(3));

        $set->add(true);
        $this->assertTrue($set->contains(true));
        $this->assertFalse($set->contains(false));

        $obj = new \stdClass();
        $obj->a = 'abc';
        $set->add($obj);
        $another = new \stdClass();
        $this->assertFalse($set->contains($another));
        $another->a = 'abc';
        $this->assertTrue($set->contains($another));
    }
}
