<?php
declare(strict_types=1);
namespace Ivory\Utils;

use PHPUnit\Framework\TestCase;

class StringUtilsTest extends TestCase
{
    public function testPregReplaceCallbackWithOffset()
    {
        $str = 'foo bar   baAz aaa  aaa';
        $re = '~ [[:alnum:]]+ ([aA]) [[:alnum:]]+ ~x';
        $expectedMatches = [
            [['bar', 4], ['a', 5]],
            [['baAz', 10], ['A', 12]],
            [['aaa', 15], ['a', 16]],
        ];
        $i = 0;
        $tester = function ($matchesWithOffsets) use ($expectedMatches, &$i) {
            self::assertSame($expectedMatches[$i], $matchesWithOffsets, "Match $i");
            $i++;
            return str_repeat('X', strlen($matchesWithOffsets[0][0]));
        };
        $repl = StringUtils::pregReplaceCallbackWithOffset($re, $tester, $str, 3, $count);
        self::assertSame('foo XXX   XXXX XXX  aaa', $repl);
        self::assertSame(3, $count);
    }

    public function testRandomHexString()
    {
        for ($i = 0; $i <= 33; $i++) {
            $s = StringUtils::randomHexString($i);
            self::assertEquals($i, strlen($s), "Error generating string of length $i");
        }

        $this->expectException(\DomainException::class);
        StringUtils::randomHexString(-1);
    }
}
