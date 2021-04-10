<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\EqualableWithCompareTo;
use Ivory\Value\Alg\IComparable;

class LtreeComparable extends Ltree implements IComparable
{
    use EqualableWithCompareTo;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function fromArray(array $labels): Ltree // declare returning LtreeComparable on PHP >= 7.4
    {
        foreach ($labels as $label) {
            self::checkLabel($label);
        }
        return new LtreeComparable($labels);
    }

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException();
        }
        if (!$other instanceof LtreeComparable) {
            throw new IncomparableException();
        }

        return ($this->toArray() <=> $other->toArray());
    }
}
