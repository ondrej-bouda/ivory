<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;
use Ivory\Value\Composite;
use PHPUnit\Framework\TestCase;

class CompositeTypeTest extends TestCase
{
    /** @var CompositeType */
    private $zeroType;
    /** @var CompositeType */
    private $intSingletonType;
    /** @var CompositeType */
    private $intTextPairType;

    protected function setUp()
    {
        parent::setUp();

        $this->zeroType = new CompositeType('s', 't0');

        $this->intSingletonType = new CompositeType('s', 't1');
        $this->intSingletonType->addAttribute('a', new IntegerType('pg_catalog', 'int4'));

        $this->intTextPairType = new CompositeType('s', 't2');
        $this->intTextPairType->addAttribute('a', new IntegerType('pg_catalog', 'int4'));
        $this->intTextPairType->addAttribute('b', new StringType('pg_catalog', 'text'));
    }

    public function testParseSimple()
    {
        $this->assertSame([], $this->zeroType->parseValue('()')->toMap());

        $this->assertSame(['a' => 1], $this->intSingletonType->parseValue('(1)')->toMap());
        $this->assertSame(['a' => null], $this->intSingletonType->parseValue('()')->toMap());

        $this->assertSame(['a' => 1, 'b' => 'ab'], $this->intTextPairType->parseValue('(1,ab)')->toMap());
        $this->assertSame(['a' => null, 'b' => ''], $this->intTextPairType->parseValue('(,"")')->toMap());
        $this->assertSame(['a' => 0, 'b' => null], $this->intTextPairType->parseValue('(0,)')->toMap());
    }

    public function testSerializeSimple()
    {
        $this->assertSame('NULL::s.t1', $this->intSingletonType->serializeValue(null));

        $this->assertSame('ROW()::s.t0', $this->zeroType->serializeValue(Composite::fromMap([])));

        $this->assertSame('ROW(1)', $this->intSingletonType->serializeValue(Composite::fromMap(['a' => 1]), false));
        $this->assertSame(
            'ROW(NULL::pg_catalog.int4)::s.t1',
            $this->intSingletonType->serializeValue(Composite::fromMap(['a' => null]))
        );
        $this->assertSame(
            "(1::pg_catalog.int4,pg_catalog.text 'ab')::s.t2",
            $this->intTextPairType->serializeValue($this->intText(1, 'ab'))
        );
        $this->assertSame(
            "(1::pg_catalog.int4,pg_catalog.text '2')::s.t2",
            $this->intTextPairType->serializeValue($this->intText(1, 2))
        );
        $this->assertSame(
            "(NULL::pg_catalog.int4,pg_catalog.text '')::s.t2",
            $this->intTextPairType->serializeValue($this->intText(null, ''))
        );
        $this->assertSame(
            '(0::pg_catalog.int4,NULL::pg_catalog.text)::s.t2',
            $this->intTextPairType->serializeValue($this->intText(0, null))
        );
    }

    private function intText($a, $b)
    {
        return Composite::fromMap(['a' => $a, 'b' => $b]);
    }
}
