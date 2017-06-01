<?php
namespace Ivory\Type\Postgresql;

use Ivory\Type\Std\IntegerType;
use Ivory\Type\Std\StringType;
use Ivory\Value\Composite;

class CompositeTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var CompositeType */
    private $adHocComposite;

    protected function setUp()
    {
        parent::setUp();

        $this->adHocComposite = new AdHocCompositeType('pg_catalog', 'record');
    }

    public function testParseSimpleUntyped()
    {
        $this->assertSame([], $this->adHocComposite->parseValue('()')->toList());
        $this->assertSame(['1'], $this->adHocComposite->parseValue('(1)')->toList());
        $this->assertSame(['ab'], $this->adHocComposite->parseValue('(ab)')->toList());
        $this->assertSame(['1', 'ab'], $this->adHocComposite->parseValue('(1,ab)')->toList());
        $this->assertSame([null, '1.2'], $this->adHocComposite->parseValue('(,1.2)')->toList());
    }

    public function testParseSpecialStrings()
    {
        $this->assertSame([' '], $this->adHocComposite->parseValue('( )')->toList());

        $this->assertSame(
            [null, 'NULL', '', '()', ',', '1\\2', 'p q', '"r"', "'", '"', ' , a \\ " ( ) \' '],
            $this->adHocComposite->parseValue(<<<'STR'
(,NULL,"","()",",","1\\2","p q","\"r\"",',"""", \, \a \\ \" \( \) ' )
STR
            )->toList()
        );
    }

    public function testParseSimpleTyped()
    {
        $this->assertSame([], $this->adHocComposite->parseValue('()')->toList());

        $this->adHocComposite->addAttribute('a', new IntegerType('pg_catalog', 'int4'));
        $this->assertSame([1], $this->adHocComposite->parseValue('(1)')->toList());
        $this->assertSame([null], $this->adHocComposite->parseValue('()')->toList());

        $this->adHocComposite->addAttribute('b', new StringType('pg_catalog', 'text'));
        $this->assertSame([1, 'ab'], $this->adHocComposite->parseValue('(1,ab)')->toList());
        $this->assertSame([null, ''], $this->adHocComposite->parseValue('(,"")')->toList());
        $this->assertSame([0, null], $this->adHocComposite->parseValue('(0,)')->toList());
    }

    public function testSerializeSimpleUntyped()
    {
        $this->assertSame('NULL', $this->adHocComposite->serializeValue(null));
        $this->assertSame('ROW()', $this->adHocComposite->serializeValue($this->val([])));
        $this->assertSame('ROW(NULL)', $this->adHocComposite->serializeValue($this->val([null])));
        $this->assertSame("ROW('1')", $this->adHocComposite->serializeValue($this->val([1])));
        $this->assertSame("ROW('ab')", $this->adHocComposite->serializeValue($this->val(['ab'])));
        $this->assertSame("('1','ab')", $this->adHocComposite->serializeValue($this->val([1, 'ab'])));
        $this->assertSame("(NULL,'1.2')", $this->adHocComposite->serializeValue($this->val([null, 1.2])));
    }

    public function testSerializeSpecialStrings()
    {
        $this->assertSame(
            <<<'STR'
(NULL,'NULL','','()',',','1\2','p q','"r"','''','"',' , \ " ''')
STR
            ,
            $this->adHocComposite->serializeValue($this->val(
                [null, 'NULL', '', '()', ',', '1\\2', 'p q', '"r"', "'", '"', ' , \\ " \'']
            ))
        );
    }

    public function testSerializeSimpleTyped()
    {
        $this->adHocComposite->addAttribute('a', new IntegerType('pg_catalog', 'int4'));
        $this->assertSame('ROW(1)', $this->adHocComposite->serializeValue($this->val([1])));
        $this->assertSame('ROW(NULL)', $this->adHocComposite->serializeValue($this->val([null])));

        $this->adHocComposite->addAttribute('b', new StringType('pg_catalog', 'text'));
        $this->assertSame("(1,'ab')", $this->adHocComposite->serializeValue($this->val([1, 'ab'])));
        $this->assertSame("(NULL,'')", $this->adHocComposite->serializeValue($this->val([null, ''])));
        $this->assertSame('(0,NULL)', $this->adHocComposite->serializeValue($this->val([0, null])));
    }

    private function val($values)
    {
        return Composite::fromList($this->adHocComposite, $values);
    }
}
