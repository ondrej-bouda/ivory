<?php
namespace Ivory\Type\Std;

use Ivory\Type\IDiscreteType;
use Ivory\Type\IRangeCanonicalFunc;

/**
 * Canonicalizes the given range to the `[)` bounds, as conventional for PostgreSQL standard canonical functions.
 *
 * The range subtype must be {@link \Ivory\Type\IDiscreteType discrete}.
 */
class ConventionalRangeCanonicalFunc implements IRangeCanonicalFunc
{
    private $subtype;

    public function __construct(IDiscreteType $subtype)
    {
        $this->subtype = $subtype;
    }

    public function canonicalize($lower, $lowerInc, $upper, $upperInc)
    {
        if ($lower !== null && !$lowerInc) {
            $lower = $this->subtype->step(1, $lower);
            $lowerInc = true;
        }

        if ($upper !== null && $upperInc) {
            $upper = $this->subtype->step(1, $upper);
            $upperInc = false;
        }

        return [$lower, $lowerInc, $upper, $upperInc];
    }
}
