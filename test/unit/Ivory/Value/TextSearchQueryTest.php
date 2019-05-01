<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class TextSearchQueryTest extends TestCase
{
    public function testFromFormat()
    {
        $q = TextSearchQuery::fromFormat('%s:ab & !%s |  %s:* & %s | %s:c', "Joe's'", 'c a t', 'super', "'", '1:0');
        $this->assertSame("'Joe''s''':ab & !'c a t' |  super:* & '''' | '1:0':c", $q->toString());
    }
}
