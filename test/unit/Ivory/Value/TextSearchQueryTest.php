<?php
namespace Ivory\Value;

class TextSearchQueryTest extends \PHPUnit\Framework\TestCase
{
    public function testFromFormat()
    {
        $q = TextSearchQuery::fromFormat('%s:ab & !%s |  %s:* & %s | %s:c', "Joe's'", 'c a t', 'super', "'", '1:0');
        $this->assertSame("'Joe''s''':ab & !'c a t' |  super:* & '''' | '1:0':c", $q->toString());
    }
}
