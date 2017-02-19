<?php
namespace Ivory\Utils;

class StringUtilsTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
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
            $this->assertSame($expectedMatches[$i], $matchesWithOffsets, "Match $i");
            $i++;
            return str_repeat('X', strlen($matchesWithOffsets[0][0]));
        };
        $repl = StringUtils::pregReplaceCallbackWithOffset($re, $tester, $str, 3, $count);
        $this->assertSame('foo XXX   XXXX XXX  aaa', $repl);
        $this->assertSame(3, $count);
    }
}
