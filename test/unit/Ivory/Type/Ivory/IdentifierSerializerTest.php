<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use PHPUnit\Framework\TestCase;

class IdentifierSerializerTest extends TestCase
{
    /** @var IdentifierSerializer */
    private $identSerializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identSerializer = new IdentifierSerializer();
    }

    public function testSerializeUnquoted(): void
    {
        self::assertSame('wheee', $this->identSerializer->serializeValue('wheee'));
        self::assertSame("_\u{010D}a\u{010D}a1\$", $this->identSerializer->serializeValue("_\u{010D}a\u{010D}a1\$"));
    }

    public function testSerializeQuoted(): void
    {
        self::assertSame('"whEEE"', $this->identSerializer->serializeValue('whEEE'));
        self::assertSame('"1whEEE"', $this->identSerializer->serializeValue('1whEEE'));
        self::assertSame('""', $this->identSerializer->serializeValue(''));
    }

    public function testSerializeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->identSerializer->serializeValue(null);
    }
}
