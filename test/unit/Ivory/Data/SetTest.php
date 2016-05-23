<?php
namespace Ivory\Data\Src;

use Ivory\Data\Set\CustomSet;
use Ivory\Data\Set\DictionarySet;
use Ivory\Relation\QueryRelation;

class SetTest extends \Ivory\IvoryTestCase
{
    public function testDictionarySet()
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

    public function testCustomSet()
    {
        $set = new CustomSet(function ($val) { return strtolower($val); });
        $set->add('Google');
        $this->assertTrue($set->contains('Google'));
        $this->assertTrue($set->contains('google'));
    }
    
    public function testSetRelation()
    {
        $conn = $this->getIvoryConnection();
        $qr = new QueryRelation($conn, 'SELECT id, name, is_active FROM artist ORDER BY name, id');

        $set = $qr->toSet('name');
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertFalse($set->contains('b-side band'));
        $this->assertFalse($set->contains('b-SIDE band'));

        $set = $qr->toSet('name', new CustomSet('strtolower'));
        $this->assertTrue($set->contains('B-Side Band'));
        $this->assertTrue($set->contains('b-side band'));
        $this->assertTrue($set->contains('b-SIDE band'));
    }
}
