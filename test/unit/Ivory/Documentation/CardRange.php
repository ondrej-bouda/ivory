<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Value\Alg\IDiscreteStepper;
use Ivory\Value\Alg\IValueComparator;
use Ivory\Value\Range;

class CardRange extends Range
{
    public static function fromBounds(
        $lower,
        $upper,
        $boundsOrLowerInc = '[)',
        ?bool $upperInc = null,
        ?IValueComparator $customComparator = null,
        ?IDiscreteStepper $customDiscreteStepper = null
    ): Range {
        return parent::fromBounds(
            $lower,
            $upper,
            $boundsOrLowerInc,
            $upperInc,
            ($customComparator ?? CardType::provideValueComparator()),
            ($customDiscreteStepper ?? CardType::provideDiscreteStepper())
        );
    }
}
