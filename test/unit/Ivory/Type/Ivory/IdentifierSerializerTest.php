<?php
namespace Ivory\Type\Ivory;

class IdentifierSerializerTest extends \PHPUnit\Framework\TestCase
{
    /** @var IdentifierSerializer */
    private $identSerializer;

    protected function setUp()
    {
        $this->identSerializer = new IdentifierSerializer();
    }

    public function testSerializeUnquoted()
    {
        $this->assertSame('wheee', $this->identSerializer->serializeValue('wheee'));
        $this->assertSame("_\u{010D}a\u{010D}a1\$", $this->identSerializer->serializeValue("_\u{010D}a\u{010D}a1\$"));
    }

    public function testSerializeQuoted()
    {
        $this->assertSame('"whEEE"', $this->identSerializer->serializeValue('whEEE'));
        $this->assertSame('"1whEEE"', $this->identSerializer->serializeValue('1whEEE'));
        $this->assertSame('""', $this->identSerializer->serializeValue(''));
    }

    public function testSerializeNull()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->identSerializer->serializeValue(null);
    }
}
