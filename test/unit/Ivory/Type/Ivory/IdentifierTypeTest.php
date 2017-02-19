<?php
namespace Ivory\Type\Ivory;

class IdentifierTypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var IdentifierType */
    private $identType;

    protected function setUp()
    {
        $this->identType = new IdentifierType();
    }

    public function testSerializeUnquoted()
    {
        $this->assertSame('wheee', $this->identType->serializeValue('wheee'));
        $this->assertSame("_\u{010D}a\u{010D}a1\$", $this->identType->serializeValue("_\u{010D}a\u{010D}a1\$"));
    }

    public function testSerializeQuoted()
    {
        $this->assertSame('"whEEE"', $this->identType->serializeValue('whEEE'));
        $this->assertSame('"1whEEE"', $this->identType->serializeValue('1whEEE'));
        $this->assertSame('""', $this->identType->serializeValue(''));
    }

    public function testSerializeNull()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->identType->serializeValue(null);
    }
}
