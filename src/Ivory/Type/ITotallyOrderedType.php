<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * Specifies that the {@link IType::parseValue()} will return comparable values.
 *
 * With the {@link Ivory\Ivory::getDefaultValueComparator() default value comparator}, "comparable" means supported by
 * {@link \Ivory\Value\Alg\ComparisonUtils::compareValues()}.
 */
interface ITotallyOrderedType extends IType
{
}
