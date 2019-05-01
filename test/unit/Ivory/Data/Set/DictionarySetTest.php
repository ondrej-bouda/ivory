<?php
declare(strict_types=1);
namespace Ivory\Data\Set;

use PHPUnit\Framework\TestCase;

class DictionarySetTest extends TestCase
{
    public function testBasic()
    {
        $set = new DictionarySet();
        self::assertSame(0, $set->count());
        self::assertFalse($set->contains(3));

        $set->add(1);
        $set->add(3);
        self::assertSame(2, $set->count());
        self::assertTrue($set->contains(3));
        self::assertFalse($set->contains(2));

        $set->add(3);
        self::assertSame(2, $set->count());
        self::assertTrue($set->contains(3));

        $set->remove(3);
        self::assertSame(1, $set->count());
        self::assertFalse($set->contains(3));

        $set->add(true);
        self::assertTrue($set->contains(true));
        self::assertFalse($set->contains(false));

        $obj = new \stdClass();
        $obj->a = 'abc';
        $set->add($obj);
        $another = new \stdClass();
        self::assertFalse($set->contains($another));
        $another->a = 'abc';
        self::assertTrue($set->contains($another)); // objects are compared as values, not as references

        self::assertSame(3, count($set));
        self::assertTrue($set->contains(1));
        $set->clear();
        self::assertSame(0, count($set));
        self::assertFalse($set->contains(1));
    }

    public function testToArray()
    {
        $set = new DictionarySet();
        $set->add(1);
        $set->add(3);

        $arr = $set->toArray();
        sort($arr); // sets do not guarantee any specific order; sort the output to compare it with the expected value
        self::assertSame([1, 3], $arr);
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
        self::assertSame([1, 3], $arr);
    }
}
