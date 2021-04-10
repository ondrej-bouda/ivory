<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\AmbiguousException;
use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Value\Alg\ITupleEvaluator;
use Ivory\Value\Alg\IEqualable;
use PHPUnit\Framework\TestCase;

class TupleTest extends TestCase
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
        self::assertSame([1, 2, null, false], $this->simpleTuple->toList());
        self::assertSame([1, 3, null, 4], $this->anonymousColsTuple->toList());
        self::assertSame([1, 3, 5, 7], $this->ambiguousColsTuple->toList());
    }

    public function testToMap()
    {
        self::assertSame(['a' => 1, 'b' => 2, 'c' => null, 'some col' => false], $this->simpleTuple->toMap());
        self::assertSame(['a' => 1, 'b' => 3], $this->anonymousColsTuple->toMap());

        try {
            $this->ambiguousColsTuple->toMap();
            self::fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }

    public function testArrayAccess()
    {
        self::assertSame(1, $this->simpleTuple[0]);
        self::assertSame(false, $this->simpleTuple[3]);
        self::assertSame(null, $this->anonymousColsTuple[2]);
        self::assertSame(5, $this->ambiguousColsTuple[2]);

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple[4];
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple[-1];
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        self::assertTrue(isset($this->simpleTuple[0]));
        self::assertFalse(isset($this->simpleTuple[-1]));
        self::assertTrue(isset($this->simpleTuple[3]));
        self::assertFalse(isset($this->simpleTuple[4]));

        try {
            $this->simpleTuple[0] = 'x';
            self::fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($this->simpleTuple[0]);
            self::fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }
    }

    public function testOverloadedAccess()
    {
        self::assertSame(2, $this->simpleTuple->b);
        self::assertSame(null, $this->simpleTuple->c);
        self::assertSame(false, $this->simpleTuple->{'some col'});
        self::assertSame(3, $this->anonymousColsTuple->b);
        self::assertSame(1, $this->ambiguousColsTuple->a);

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->ambiguousColsTuple->b;
            self::fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple->d;
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        self::assertTrue(isset($this->simpleTuple->a));
        self::assertFalse(isset($this->simpleTuple->d));
        self::assertTrue(isset($this->ambiguousColsTuple->b));


        try {
            $this->simpleTuple->a = 'x';
            self::fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }

        try {
            unset($this->simpleTuple->a);
            self::fail(ImmutableException::class . ' was expected');
        } catch (ImmutableException $e) {
        }
    }

    public function testValue()
    {
        self::assertSame(2, $this->simpleTuple->value('b'));
        self::assertSame(null, $this->simpleTuple->value(2));
        self::assertSame(16, $this->ambiguousColsTuple->value(
            function (ITuple $tuple) { return array_sum($tuple->toList()); }
        ));
        self::assertSame(4, $this->anonymousColsTuple->value(
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
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->simpleTuple->value(4);
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->anonymousColsTuple->value('c');
            self::fail(UndefinedColumnException::class . ' was expected');
        } catch (UndefinedColumnException $e) {
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $val = $this->ambiguousColsTuple->value('b');
            self::fail(AmbiguousException::class . ' was expected');
        } catch (AmbiguousException $e) {
        }
    }

    public function testEqualable()
    {
        $odd1Tuple = Tuple::fromMap(['a' => new TupleTestEqualable(1), 'b' => null]);
        $odd2Tuple = Tuple::fromMap(['a' => new TupleTestEqualable(3), 'b' => null]);
        self::assertTrue($odd1Tuple->equals($odd2Tuple));
        self::assertTrue($odd2Tuple->equals($odd1Tuple));

        $evenTuple = Tuple::fromMap(['a' => new TupleTestEqualable(2), 'b' => null]);
        self::assertFalse($odd1Tuple->equals($evenTuple));
        self::assertFalse($evenTuple->equals($odd1Tuple));
    }
}


class TupleTestEqualable implements IEqualable
{
    private $num;

    public function __construct(int $num)
    {
        $this->num = $num;
    }

    public function equals($other): bool
    {
        if (!$other instanceof TupleTestEqualable) {
            return false;
        }
        return (($this->num % 2) == ($other->num % 2));
    }
}
