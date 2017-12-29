<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

class HstoreTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var HstoreType */
    private $hstoreType;

    protected function setUp()
    {
        parent::setUp();

        $this->hstoreType = new HstoreType('public', 'hstore');
    }

    public function testSerializeValue()
    {
        $this->assertSame('NULL', $this->hstoreType->serializeValue(null));
        $this->assertSame('', $this->hstoreType->serializeValue([]));
        $this->assertSame('"1"=>"2"', $this->hstoreType->serializeValue([1 => 2]));
        $this->assertSame(
            '"a"=>"1","-32"=>NULL,"null str"=>"NULL"," space "=>"a \\"quoted str\\"",""=>"","\\\\"=>"\\\\\\\\\\""',
            $this->hstoreType->serializeValue(
                ['a' => 1, -32 => null, 'null str' => 'NULL', ' space ' => 'a "quoted str"', '' => '', '\\' => '\\\\"']
            )
        );

        $obj = new \stdClass();
        $obj->att = 'val';
        $obj->nul = null;
        $obj->other = 1;
        $this->assertSame('"att"=>"val","nul"=>NULL,"other"=>"1"', $this->hstoreType->serializeValue($obj));

        $arrObj = new \ArrayObject([1, 2, 3]);
        $this->assertSame('"0"=>"1","1"=>"2","2"=>"3"', $this->hstoreType->serializeValue($arrObj));

        try {
            $this->hstoreType->serializeValue('wheee');
            $this->fail('Exception was expected due to invalid value to be serialized to hstore.');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testParseValue()
    {
        $this->assertSame([], $this->hstoreType->parseValue(''));
        $this->assertSame(['a' => 'b'], $this->hstoreType->parseValue('"a"=>"b"'));
        $this->assertSame(
            ['a' => '1', -32 => null, 'null str' => 'NULL', ' space ' => 'a "quoted str"', '' => '', '\\' => '\\\\"'],
            $this->hstoreType->parseValue(
                '"a" => "1", "-32"=>NULL,"null str"   => "NULL"," space "' . "\t" . '=>"a \\"quoted str\\""  ,   ' .
                '""=>"", "\\\\" => "\\\\\\\\\\""'
            )
        );
        $this->assertSame(
            ['a' => 'b', 1 => '2', '' => ''],
            $this->hstoreType->parseValue('a=>b,  1  =>   2,""=>""')
        );

        try {
            $this->hstoreType->parseValue('wheee');
            $this->fail('Exception was expected due to invalid hstore value.');
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * @depends testParseValue
     */
    public function testParseLongValue()
    {
        // Some strings are too long for being processed by PCRE with JIT enabled. E.g., on Windows PHP 7.0.4, more than
        // 2725 in the str_repeat below results in an error.
        $str = '"a"=>"' . str_repeat('a', 10000) . '"';
        $this->assertSame(
            ['a' => str_repeat('a', 10000)],
            $this->hstoreType->parseValue($str)
        );
    }
}
