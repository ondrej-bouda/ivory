<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use PHPUnit\Framework\TestCase;

class LikeExpressionSerializerTest extends TestCase
{
    public function testBasic()
    {
        $serializer = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);

        self::assertSame("'McDonald''s%'", $serializer->serializeValue("McDonald's"));
        self::assertSame('NULL', $serializer->serializeValue(null));
    }

    public function testWildcardModes()
    {
        $exact = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_NONE);
        $prepend = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_PREPEND);
        $append = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);
        $both = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_BOTH);

        self::assertSame("'foo'", $exact->serializeValue('foo'));
        self::assertSame("'%foo'", $prepend->serializeValue('foo'));
        self::assertSame("'foo%'", $append->serializeValue('foo'));
        self::assertSame("'%foo%'", $both->serializeValue('foo'));
    }

    public function testSpecialChars()
    {
        $serializer = new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND);

        self::assertSame("'spec\\_ial \\%char\\\\s%'", $serializer->serializeValue('spec_ial %char\\s'));
    }
}
