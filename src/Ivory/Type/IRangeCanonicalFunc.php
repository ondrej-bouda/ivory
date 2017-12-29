<?php
declare(strict_types=1);
namespace Ivory\Type;

/**
 * Function used for converting a range to its canonical form.
 *
 * E.g., the PostgreSQL standard canonical function used on the `int4range` type converts the range `[1,2]` to `[1,3)`.
 */
interface IRangeCanonicalFunc
{
    /**
     * Canonicalizes the given range.
     *
     * The given lower bound is guaranteed to be less than, or equal to the given upper bound.
     *
     * As the result, the method must return the new range bounds. Whether the canonicalization results in actually an
     * empty range is out of interest of this method - the caller is responsible to check out for that eventuality.
     *
     * @param mixed $lower the lower range bound; <tt>null</tt> if minus infinity
     * @param bool $lowerInc whether the lower bound is inclusive
     * @param mixed $upper the upper range bound; <tt>null</tt> if plus infinity
     * @param bool $upperInc whether the upper bound is inclusive
     * @return array quadruplet (lower, lowerInc, upper, upperInc) representing the canonical form of the range
     */
    function canonicalize($lower, bool $lowerInc, $upper, bool $upperInc): array;
}
