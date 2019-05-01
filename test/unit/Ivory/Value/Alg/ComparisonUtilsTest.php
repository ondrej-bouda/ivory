<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

use PHPUnit\Framework\TestCase;

class ComparisonUtilsTest extends TestCase
{
    public function testCompareArrays()
    {
        self::assertTrue(ComparisonUtils::compareArrays([], [1]) < 0);
        self::assertTrue(ComparisonUtils::compareArrays([1], []) > 0);

        self::assertSame(0, ComparisonUtils::compareArrays([1, 3], [1, 3]));
        self::assertTrue(ComparisonUtils::compareArrays([1, 2, 3], [1, 3]) < 0);
        self::assertTrue(ComparisonUtils::compareArrays([1, 3], [1, 2, 3]) > 0);
        self::assertTrue(ComparisonUtils::compareArrays([1, 3, 3], [1, 3]) > 0);
        self::assertTrue(ComparisonUtils::compareArrays([1, 3], [1, 3, 3]) < 0);

        self::assertSame(0, ComparisonUtils::compareArrays([[1], [3]], [[1], [3]]));
        self::assertTrue(ComparisonUtils::compareArrays([[1], [2], [3]], [[1], [3], [2]]) < 0);
        self::assertTrue(ComparisonUtils::compareArrays([[2], [1], [3]], [[1], [3], [2]]) > 0);
        self::assertTrue(ComparisonUtils::compareArrays([[1], [3], [3]], [[1], [3], [2]]) > 0);
        self::assertTrue(ComparisonUtils::compareArrays([[1], [3], [2]], [[1], [3], [3]]) < 0);

        self::assertTrue(ComparisonUtils::compareArrays([1 => [1], [3]], [[1], [3]]) > 0);
    }
}
