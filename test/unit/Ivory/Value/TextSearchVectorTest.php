<?php
declare(strict_types=1);

namespace Ivory\Value;

class TextSearchVectorTest extends \PHPUnit\Framework\TestCase
{
    public function testFromSet()
    {
        $v = TextSearchVector::fromSet(['a', 'fat', 'cat', 'sat', 'on', 'a', 'mat', 'and', 'ate', 'a', 'fat', 'rat']);
        $this->assertSame(
            [
                'a' => null,
                'and' => null,
                'ate' => null,
                'cat' => null,
                'fat' => null,
                'mat' => null,
                'on' => null,
                'rat' => null,
                'sat' => null,
            ],
            $v->getLexemes()
        );
    }

    public function testFromList()
    {
        $v = TextSearchVector::fromList(['a', 'fat', 'cat', 'sat', 'on', 'a', 'mat', 'and', 'ate', 'a', 'fat', 'rat']);
        $this->assertSame(
            [
                'a' => [[1, 'D'], [6, 'D'], [10, 'D']],
                'and' => [[8, 'D']],
                'ate' => [[9, 'D']],
                'cat' => [[3, 'D']],
                'fat' => [[2, 'D'], [11, 'D']],
                'mat' => [[7, 'D']],
                'on' => [[5, 'D']],
                'rat' => [[12, 'D']],
                'sat' => [[4, 'D']],
            ],
            $v->getLexemes()
        );
    }

    public function testFromString()
    {
        $v = TextSearchVector::fromString('a fat cat sat on a mat and ate a fat rat');
        $this->assertSame(
            [
                'a' => null,
                'and' => null,
                'ate' => null,
                'cat' => null,
                'fat' => null,
                'mat' => null,
                'on' => null,
                'rat' => null,
                'sat' => null,
            ],
            $v->getLexemes()
        );

        $quotes = TextSearchVector::fromString("spaces\tand  quotes  '  '   'Joe''s' '' ''''''");
        $this->assertSame(
            [
                '  ' => null,
                "''" => null,
                "Joe's" => null,
                'and' => null,
                'quotes' => null,
                'spaces' => null,
            ],
            $quotes->getLexemes()
        );
    }

    public function testFromOrderedString()
    {
        $v = TextSearchVector::fromOrderedString('a fat cat sat on a mat and ate a fat rat');
        $this->assertSame(
            [
                'a' => [[1, 'D'], [6, 'D'], [10, 'D']],
                'and' => [[8, 'D']],
                'ate' => [[9, 'D']],
                'cat' => [[3, 'D']],
                'fat' => [[2, 'D'], [11, 'D']],
                'mat' => [[7, 'D']],
                'on' => [[5, 'D']],
                'rat' => [[12, 'D']],
                'sat' => [[4, 'D']],
            ],
            $v->getLexemes()
        );
    }
}
