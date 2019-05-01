<?php
declare(strict_types=1);
namespace Ivory\Data\Set;

use PHPUnit\Framework\TestCase;

class DictionarySetTest extends TestCase
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
        $this->assertTrue($set->contains($another)); // objects are compared as values, not as references

        $this->assertSame(3, count($set));
        $this->assertTrue($set->contains(1));
        $set->clear();
        $this->assertSame(0, count($set));
        $this->assertFalse($set->contains(1));
    }

    public function testToArray()
    {
        $set = new DictionarySet();
        $set->add(1);
        $set->add(3);

        $arr = $set->toArray();
        sort($arr); // sets do not guarantee any specific order; sort the output to compare it with the expected value
        $this->assertSame([1, 3], $arr);
    }

    public function testGenerateItems()
    {
        $set = new DictionarySet();
        $set->add(1);
        $set->add(3);

        $arr = [];
        foreach ($set->generateItems() as $item) {
            $arr[] = $item;
        }

        sort($arr); // sets do not guarantee any specific order; sort the output to compare it with the expected value
        $this->assertSame([1, 3], $arr);
    }
}
