<?php
declare(strict_types=1);

namespace Ivory\Type\Ivory;

class LikeExpressionSerializerTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $serializer = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);

        $this->assertSame("'McDonald''s%'", $serializer->serializeValue("McDonald's"));
        $this->assertSame('NULL', $serializer->serializeValue(null));
    }

    public function testWildcardModes()
    {
        $exact = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_NONE);
        $prepend = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_PREPEND);
        $append = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);
        $both = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_BOTH);

        $this->assertSame("'foo'", $exact->serializeValue('foo'));
        $this->assertSame("'%foo'", $prepend->serializeValue('foo'));
        $this->assertSame("'foo%'", $append->serializeValue('foo'));
        $this->assertSame("'%foo%'", $both->serializeValue('foo'));
    }

    public function testSpecialChars()
    {
        $serializer = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);

        $this->assertSame("'spec\\_ial \\%char\\\\s%'", $serializer->serializeValue('spec_ial %char\\s'));
    }
}
