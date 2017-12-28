<?php
declare(strict_types=1);

namespace Ivory\Relation;

use Ivory\Exception\AmbiguousException;
use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Utils\IEqualable;

class TupleTest extends \PHPUnit\Framework\TestCase
{
    /** @var ITuple */
    private $simpleTuple;
    /** @var ITuple */
    private $anonymousColsTuple;
    /** @var ITuple */
    private $ambiguousColsTuple;

    protected function setUp()
    {
        parent::setUp();

        $this->simpleTuple = Tuple::fromMap([
            'a' => 1,
            'b' => 2,
            'c' => null,
            'some col' => false,
        ]);

        $this->anonymousColsTuple = new Tuple(
            [1, 3, null, 4],
            ['a' => 0, 'b' => 1] // the other two columns are anonymous
        );

        $this->ambiguousColsTuple = new Tuple(
            [1, 3, 5, 7],
            ['a' => 0, 'b' => Tuple::AMBIGUOUS_COL] // multiple columns are named "b"
        );
    }

    public function testToList()
    {
        $this->assertSame([1, 2, null, false], $this->simpleTuple->toList());
        $this->assertSame([1, 3, null, 4], $this->anonymousColsTuple->toList());
        $this->assertSame([1, 3, 5, 7], $this->ambiguousColsTuple->toList());
    }

    public function testToMap()
    {
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => null, 'some col' => false], $this->simpleTuple->toMap());
        $this->assertSame(['a' => 1, 'b' => 3], $this->anonymousColsTuple->toMap());

        try {
            $this->ambiguousColsTuple->toMap();
            $this->fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }

    public function testArrayAccess()
    {
        $this->assertSame(1, $this->simpleTuple[0]);
        $this->assertSame(false, $this->simpleTuple[3]);
        $this->assertSame(null, $this->anonymousColsTuple[2]);
        $this->assertSame(5, $this->ambiguousColsTuple[2]);

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple[4];
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple[-1];
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        $this->assertTrue(isset($this->simpleTuple[0]));
        $this->assertFalse(isset($this->simpleTuple[-1]));
        $this->assertTrue(isset($this->simpleTuple[3]));
        $this->assertFalse(isset($this->simpleTuple[4]));

        try {
            $this->simpleTuple[0] = 'x';
            $this->fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($this->simpleTuple[0]);
            $this->fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }
    }

    public function testOverloadedAccess()
    {
        $this->assertSame(2, $this->simpleTuple->b);
        $this->assertSame(null, $this->simpleTuple->c);
        $this->assertSame(false, $this->simpleTuple->{'some col'});
        $this->assertSame(3, $this->anonymousColsTuple->b);
        $this->assertSame(1, $this->ambiguousColsTuple->a);

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->ambiguousColsTuple->b;
            $this->fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple->d;
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        $this->assertTrue(isset($this->simpleTuple->a));
        $this->assertFalse(isset($this->simpleTuple->d));
        $this->assertTrue(isset($this->ambiguousColsTuple->b));


        try {
            $this->simpleTuple->a = 'x';
            $this->fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($this->simpleTuple->a);
            $this->fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }
    }

    public function testValue()
    {
        $this->assertSame(2, $this->simpleTuple->value('b'));
        $this->assertSame(null, $this->simpleTuple->value(2));
        $this->assertSame(16, $this->ambiguousColsTuple->value(
            function (ITuple $tuple) { return array_sum($tuple->toList()); }
        ));
        $this->assertSame(4, $this->anonymousColsTuple->value(
            new class() implements ITupleEvaluator {
                public function evaluate(ITuple $tuple)
                {
                    return $tuple->a + $tuple->b;
                }
            }
        ));

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple->value(-1);
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple->value(4);
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->anonymousColsTuple->value('c');
            $this->fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->ambiguousColsTuple->value('b');
            $this->fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }

    public function testEqualable()
    {
        $odd1Tuple = Tuple::fromMap(['a' => new TupleTest__Equalable(1), 'b' => null]);
        $odd2Tuple = Tuple::fromMap(['a' => new TupleTest__Equalable(3), 'b' => null]);
        $this->assertTrue($odd1Tuple->equals($odd2Tuple));
        $this->assertTrue($odd2Tuple->equals($odd1Tuple));

        $evenTuple = Tuple::fromMap(['a' => new TupleTest__Equalable(2), 'b' => null]);
        $this->assertFalse($odd1Tuple->equals($evenTuple));
        $this->assertFalse($evenTuple->equals($odd1Tuple));
    }
}


class TupleTest__Equalable implements IEqualable
{
    private $num;

    public function __construct(int $num)
    {
        $this->num = $num;
    }

    public function equals($object): ?bool
    {
        if (!$object instanceof TupleTest__Equalable) {
            return false;
        }
        return (($this->num % 2) == ($object->num % 2));
    }
}
