<?php
namespace Ivory\Type\Ivory;

class IdentifierTypeTest extends \PHPUnit_Framework_TestCase
{
    /** @var IdentifierType */
    private $identType;

    protected function setUp()
    {
        $this->identType = new IdentifierType();
    }

    public function testSerializeUnquoted()
    {
        $this->assertSame('whEEE', $this->identType->serializeValue('whEEE'));
        $this->assertSame("_\u{010D}a\u{010D}a1\$", $this->identType->serializeValue("_\u{010D}a\u{010D}a1\$"));
    }

    public function testSerializeQuoted()
    {
        $this->assertSame('"1whEEE"', $this->identType->serializeValue('1whEEE'));
        $this->assertSame('""', $this->identType->serializeValue(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSerializeNull()
    {
        $this->identType->serializeValue(null);
    }
}
