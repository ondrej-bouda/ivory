<?php
namespace Ivory\Value;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UnsupportedException;
use Ivory\Type\IDiscreteType;
use Ivory\Type\IRangeCanonicalFunc;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Utils\IComparable;

/**
 * A range of values.
 *
 * The class resembles the range values manipulated by PostgreSQL. Just a brief summary:
 * - the range is defined above a type of values, called "subtype" (see {@link Range::getSubtype()});
 * - the subtype must have a total order;
 * - a range may be empty, meaning it contains nothing (see {@link Range::isEmpty()} for distinguishing it)
 * - a non-empty range has the lower and the upper bounds, either of which may either be inclusive or exclusive (see
 *   {@link Range::getLower()} and {@link Range::getUpper()} for getting the boundaries; also see
 *   {@link Range::isLowerInc()} and {@link Range::isUpperInc()} for finding out whichever boundary is inclusive);
 * - a range may be unbounded in either direction, covering all subtype values from the range towards minus infinity or
 *   towards infinity, respectively;
 * - a range might even cover just a single point ({@link Range::isSinglePoint()} may be used for checking out).
 *
 * Ranges of some types may have a canonical function, the purpose of which is to convert semantically equivalent ranges
 * on {@link \Ivory\Type\IDiscreteType discrete types} to syntactically equivalent ranges. E.g., one such function might
 * convert the range `[1,3]` to `[1,4)`. Like in PostgreSQL, such canonicalization is performed automatically upon
 * constructing a range. If other than canonical representation of a range is desired, e.g., for presenting the range
 * using both bounds inclusive, method {@link Range::toBounds()} may be useful.
 *
 * A range is `IComparable` to another range. Two ranges are equal only if they are above the same subtype and if the
 * effective range equals.
 *
 * Alternatively to the {@link Range::getLower()} and {@link Range::getUpper()} methods, `ArrayAccess` may be used using
 * indexes `0` and `1`, respectively, to get the lower and upper bound. Either of them returns `null` if the range is
 * empty or unbounded in the respective direction.
 *
 * Note the range value is immutable, i.e., once constructed, its values cannot be changed. Thus, `ArrayAccess` write
 * operations ({@link \ArrayAccess::offsetSet()} and {@link \ArrayAccess::offsetUnset()}) throw an
 * {@link \Ivory\Exception\ImmutableException}.
 *
 * @see http://www.postgresql.org/docs/9.4/static/rangetypes.html
 */
class Range implements IComparable, \ArrayAccess
{
    /** @var ITotallyOrderedType */
    private $subtype;
    /** @var IRangeCanonicalFunc */
    private $canonicalFunc;
    /** @var bool */
    private $empty;
    /** @var mixed */
    private $lower;
    /** @var mixed */
    private $upper;
    /** @var bool */
    private $lowerInc;
    /** @var bool */
    private $upperInc;


    //region construction

    /**
     * Creates a new range with given lower and upper bounds.
     *
     * There are two ways of calling this factory method: whether the bounds are inclusive or exclusive may be
     * specified:
     * - either by passing a specification string as the `$boundsOrLowerInc` argument (e.g., `'[)'`),
     * - or by passing two boolean values as the `$boundsOrLowerInc` and `$upperInc` telling whichever bound is
     *   inclusive.
     *
     * If the lower bound is greater than the upper bound, or if both bounds are equal but any of them is exclusive, an
     * empty range gets created, forgetting the given upper and lower bound (just as PostgreSQL does). For creating an
     * empty range explicitly, see {@link createEmpty()}.
     *
     * @param ITotallyOrderedType $subtype the range subtype
     * @param IRangeCanonicalFunc $canonicalFunc if given, the range will be created in the canonical form according to
     *                                             this function;
     *                                           skip with <tt>null</tt> to create the range as is;
     *                                           NOTE: while one could expect the canonical function usage at the range
     *                                             *type* level, passing it to this factory method makes it clear that
     *                                             if no canonical function is given, the range will not get
     *                                             canonicalized
     * @param mixed $lower the range lower bound, or <tt>null</tt> if unbounded
     * @param mixed $upper the range upper bound, or <tt>null</tt> if unbounded
     * @param bool|string $boundsOrLowerInc either the string of complete bounds specification, or a boolean telling
     *                                        whether the lower bound is inclusive;
     *                                      the complete specification is similar to PostgreSQL - it is a two-character
     *                                        string of <tt>'['</tt> or <tt>'('</tt>, and <tt>']'</tt> or <tt>')'</tt>
     *                                        (brackets denoting an inclusive bound, parentheses denoting an exclusive
     *                                        bound;
     *                                      when the boolean is used, also the <tt>$upperInc</tt> argument is used;
     *                                      either bound specification is irrelevant if the corresponding range edge is
     *                                        <tt>null</tt> - the range is open by definition on that side, and thus
     *                                        will be created as such
     * @param bool $upperInc whether the upper bound is inclusive;
     *                       only relevant if <tt>$boundsOrLowerInc</tt> is also boolean
     * @return Range
     */
    public static function createFromBounds(
        ITotallyOrderedType $subtype,
        IRangeCanonicalFunc $canonicalFunc = null,
        $lower,
        $upper,
        $boundsOrLowerInc = '[)',
        $upperInc = null
    ) {
        list($loInc, $upInc) = self::processBoundSpec($boundsOrLowerInc, $upperInc);

        if ($lower !== null && $upper !== null) {
            $comp = $subtype->compareValues($lower, $upper);
            if ($comp > 0) {
                return self::createEmpty($subtype);
            } elseif ($comp == 0) {
                if (!$loInc || !$upInc) {
                    return self::createEmpty($subtype);
                }
            }
        } else {
            if ($lower === null) {
                $loInc = false;
            }
            if ($upper === null) {
                $upInc = false;
            }
        }

        if ($canonicalFunc !== null) {
            list($lower, $loInc, $upper, $upInc) = $canonicalFunc->canonicalize($lower, $loInc, $upper, $upInc);

            /* Check everything once again:
             * - to find out whether the range became empty after canonicalization, and
             * - as a defensive measure against poorly written canonical functions.
             */
            if ($lower !== null && $upper !== null) {
                $comp = $subtype->compareValues($lower, $upper);
                if ($comp > 0) {
                    return self::createEmpty($subtype);
                } elseif ($comp == 0) {
                    if (!$loInc || !$upInc) {
                        return self::createEmpty($subtype);
                    }
                }
            } else {
                if ($lower === null) {
                    $loInc = false;
                }
                if ($upper === null) {
                    $upInc = false;
                }
            }
        }

        return new Range($subtype, $canonicalFunc, false, $lower, $upper, $loInc, $upInc);
    }

    /**
     * Creates a new empty range.
     *
     * @param ITotallyOrderedType $subtype the range subtype
     * @return Range
     */
    public static function createEmpty(ITotallyOrderedType $subtype)
    {
        return new Range($subtype, null, true, null, null, null, null);
    }

    private static function processBoundSpec($boundsOrLowerInc = '[)', $upperInc = null)
    {
        if (is_string($boundsOrLowerInc)) {
            if ($upperInc !== null) {
                trigger_error('$upperInc is irrelevant - string specification for $boundsOrLowerInc given', E_USER_NOTICE);
            }
            // OPT: measure whether preg_match('~^[[(][\])]$~', $boundsOrLowerInc) would not be faster
            if (strlen($boundsOrLowerInc) != 2 || // OPT: isset($boundsOrLowerInc[2] might be faster
                strpos('([', $boundsOrLowerInc[0]) === false ||
                strpos(')]', $boundsOrLowerInc[1]) === false
            ) {
                $msg = "Invalid bounds inclusive/exclusive specification string: $boundsOrLowerInc";
                throw new \InvalidArgumentException($msg);
            }

            $loInc = ($boundsOrLowerInc[0] == '[');
            $upInc = ($boundsOrLowerInc[1] == ']');
        } else {
            $loInc = (bool)$boundsOrLowerInc;
            $upInc = (bool)$upperInc;
        }

        return [$loInc, $upInc];
    }


    private function __construct(
        ITotallyOrderedType $subtype,
        $canonicalFunc,
        $empty,
        $lower,
        $upper,
        $lowerInc,
        $upperInc
    ) {
        $this->subtype = $subtype;
        $this->canonicalFunc = $canonicalFunc;
        $this->empty = $empty;
        $this->lower = $lower;
        $this->upper = $upper;
        $this->lowerInc = $lowerInc;
        $this->upperInc = $upperInc;
    }

    //endregion

    //region getters

    final public function getSubtype()
    {
        return $this->subtype;
    }

    /**
     * @return bool whether the range is empty
     */
    final public function isEmpty()
    {
        return $this->empty;
    }

    /**
     * @return mixed lower bound, or <tt>null</tt> if the range is lower-unbounded or empty
     */
    final public function getLower()
    {
        return $this->lower;
    }

    /**
     * @return mixed upper bound, or <tt>null</tt> if the range is upper-unbounded or empty
     */
    final public function getUpper()
    {
        return $this->upper;
    }

    /**
     * @return bool|null whether the range includes its lower bound, or <tt>null</tt> if the range is empty;
     *                   for lower-unbounded ranges, <tt>false</tt> is returned by definition
     */
    final public function isLowerInc()
    {
        return $this->lowerInc;
    }

    /**
     * @return bool|null whether the range includes its upper bound, or <tt>null</tt> if the range is empty;
     *                   for upper-unbounded ranges, <tt>false</tt> is returned by definition
     */
    final public function isUpperInc()
    {
        return $this->upperInc;
    }

    /**
     * @return string|null the bounds inclusive/exclusive specification, as accepted by {@link createFromBounds()}, or
     *                     <tt>null</tt> if the range is empty
     */
    final public function getBoundsSpec()
    {
        if ($this->empty) {
            return null;
        } else {
            return ($this->lowerInc ? '[' : '(') . ($this->upperInc ? ']' : ')');
        }
    }

    /**
     * @return bool whether the range is just a single point
     */
    final public function isSinglePoint()
    {
        if ($this->empty || $this->lower === null || $this->upper === null) {
            return false;
        }

        $cmp = $this->subtype->compareValues($this->lower, $this->upper);
        if ($cmp == 0) {
            return ($this->lowerInc && $this->upperInc);
        }
        if ($this->lowerInc && $this->upperInc) { // optimization
            return false;
        }
        if ($this->subtype instanceof IDiscreteType) {
            $lo = ($this->lowerInc ? $this->lower : $this->subtype->step(1, $this->lower));
            $up = ($this->upperInc ? $this->upper : $this->subtype->step(-1, $this->upper));
            return ($this->subtype->compareValues($lo, $up) == 0);
        } else {
            return false;
        }
    }

    /**
     * Returns the range bounds according to the requested bound specification.
     *
     * **Only defined on ranges of {@link IDiscreteType discrete} subtypes.**
     *
     * E.g., on an integer range `(null,3)`, if bounds `[]` are requested, a pair of `null` and `2` is returned.
     *
     * @param bool|string $boundsOrLowerInc either the string of complete bounds specification, or a boolean telling
     *                                        whether the lower bound is inclusive;
     *                                      the complete specification is similar to PostgreSQL - it is a two-character
     *                                        string of <tt>'['</tt> or <tt>'('</tt>, and <tt>']'</tt> or <tt>')'</tt>
     *                                        (brackets denoting an inclusive bound, parentheses denoting an exclusive
     *                                        bound;
     *                                      when the boolean is used, also the <tt>$upperInc</tt> argument is used
     * @param bool $upperInc whether the upper bound is inclusive;
     *                       only relevant if <tt>$boundsOrLowerInc</tt> is also boolean
     * @return array|null pair of the lower and upper bound, or <tt>null</tt> if the range is empty
     * @throws UnsupportedException if the range subtype is not an {@link IDiscreteType}
     */
    public function toBounds($boundsOrLowerInc, $upperInc = null)
    {
        if (!$this->subtype instanceof IDiscreteType) {
            throw new UnsupportedException('Range subtype is not ' . IDiscreteType::class . ', cannot convert bounds');
        }

        if ($this->empty) {
            return null;
        }

        list($loInc, $upInc) = self::processBoundSpec($boundsOrLowerInc, $upperInc);

        if ($this->lower === null) {
            $lo = null;
        } else {
            $lo = $this->lower;
            $step = $loInc - $this->lowerInc;
            if ($step) {
                $lo = $this->subtype->step($step, $lo);
            }
        }

        if ($this->upper === null) {
            $up = null;
        } else {
            $up = $this->upper;
            $step = $this->upperInc - $upInc;
            if ($step) {
                $up = $this->subtype->step($step, $up);
            }
        }

        return [$lo, $up];
    }

    final public function __toString()
    {
        if ($this->empty) {
            return 'empty';
        }

        $bounds = $this->getBoundsSpec();
        return sprintf('%s%s,%s%s',
            $bounds[0],
            ($this->lower === null ? '-infinity' : $this->lower),
            ($this->upper === null ? 'infinity' : $this->upper),
            $bounds[1]
        );
    }

    //endregion

    //region range operations

    /**
     * @param mixed $element a value of the range subtype
     * @return bool whether this range contains the given element;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function containsElement($element)
    {
        if ($element === null) {
            return null;
        }
        if ($this->empty) {
            return false;
        }

        if ($this->lower !== null) {
            $cmp = $this->subtype->compareValues($element, $this->lower);
            if ($cmp < 0 || ($cmp == 0 && !$this->lowerInc)) {
                return false;
            }
        }

        if ($this->upper !== null) {
            $cmp = $this->subtype->compareValues($element, $this->upper);
            if ($cmp > 0 || ($cmp == 0 && !$this->upperInc)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $element value of the range subtype
     * @return bool <tt>true</tt> iff this range is left of the given element - value of the range subtype;
     *              <tt>false</tt> otherwise, especially if this range is empty;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function leftOfElement($element)
    {
        if ($element === null) {
            return null;
        }
        if ($this->empty) {
            return false;
        }

        if ($this->upper === null) {
            return false;
        }
        $cmp = $this->subtype->compareValues($element, $this->upper);
        return ($cmp > 0 || ($cmp == 0 && !$this->upperInc));
    }

    /**
     * @param mixed $element value of the range subtype
     * @return bool <tt>true</tt> iff this range is right of the given element - value of the range subtype;
     *              <tt>false</tt> otherwise, especially if this range is empty;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function rightOfElement($element)
    {
        if ($element === null) {
            return null;
        }
        if ($this->empty) {
            return false;
        }

        if ($this->lower === null) {
            return false;
        }
        $cmp = $this->subtype->compareValues($element, $this->lower);
        return ($cmp < 0 || ($cmp == 0 && !$this->lowerInc));
    }

    /**
     * @param Range $other a range of the same subtype as this range
     * @return bool whether this range entirely contains the other range;
     *              an empty range is considered to be contained in any range, even an empty one;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function containsRange($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        if ($other->empty) {
            return true;
        }
        if ($this->empty) {
            return false;
        }

        if ($this->lower !== null) {
            if ($other->lower === null) {
                return false;
            } else {
                $cmp = $this->subtype->compareValues($this->lower, $other->lower);
                if ($cmp > 0 || ($cmp == 0 && !$this->lowerInc && $other->lowerInc)) {
                    return false;
                }
            }
        }

        if ($this->upper !== null) {
            if ($other->upper === null) {
                return false;
            } else {
                $cmp = $this->subtype->compareValues($this->upper, $other->upper);
                if ($cmp < 0 || ($cmp == 0 && !$this->upperInc && $other->upperInc)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Range $other a range of the same subtype as this range
     * @return bool whether this range is entirely contained in the other range;
     *              an empty range is considered to be contained in any range, even an empty one;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function containedInRange($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        return $other->containsRange($this);
    }

    /**
     * @param Range $other a range of the same subtype as this range
     * @return bool whether this and the other range overlap, i.e., have a non-empty intersection;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function overlaps($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        if ($this->empty || $other->empty) {
            return false;
        }

        if ($this->lower !== null && $other->upper !== null) {
            $cmp = $this->subtype->compareValues($this->lower, $other->upper);
            if ($cmp > 0 || ($cmp == 0 && (!$this->lowerInc || !$other->upperInc))) {
                return false;
            }
        }
        if ($other->lower !== null && $this->upper !== null) {
            $cmp = $this->subtype->compareValues($other->lower, $this->upper);
            if ($cmp > 0 || ($cmp == 0 && (!$other->lowerInc || !$this->upperInc))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Computes the intersection of this range with another range.
     *
     * @param Range $other a range of the same subtype as this range
     * @return Range intersection of this and the other range
     *               <tt>null</tt> on <tt>null</tt> input
     */
    public function intersect($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        if ($this->empty) {
            return $this;
        }
        if ($other->empty) {
            return $other;
        }

        if ($this->lower === null) {
            $lo = $other->lower;
            $loInc = $other->lowerInc;
        } elseif ($other->lower === null) {
            $lo = $this->lower;
            $loInc = $this->lowerInc;
        } else {
            $cmp = $this->subtype->compareValues($this->lower, $other->lower);
            if ($cmp < 0) {
                $lo = $other->lower;
                $loInc = $other->lowerInc;
            } elseif ($cmp > 0) {
                $lo = $this->lower;
                $loInc = $this->lowerInc;
            } else {
                $lo = $this->lower;
                $loInc = ($this->lowerInc && $other->lowerInc);
            }
        }

        if ($this->upper === null) {
            $up = $other->upper;
            $upInc = $other->upperInc;
        } elseif ($other->upper === null) {
            $up = $this->upper;
            $upInc = $this->upperInc;
        } else {
            $cmp = $this->subtype->compareValues($this->upper, $other->upper);
            if ($cmp < 0) {
                $up = $this->upper;
                $upInc = $this->upperInc;
            } elseif ($cmp > 0) {
                $up = $other->upper;
                $upInc = $other->upperInc;
            } else {
                $up = $this->upper;
                $upInc = ($this->upperInc && $other->upperInc);
            }
        }

        return self::createFromBounds($this->subtype, $this->canonicalFunc, $lo, $up, $loInc, $upInc);
    }

    /**
     * @return bool whether the range is finite, i.e., neither starts nor ends in the infinity;
     *              note that an empty range is considered as finite
     */
    final public function isFinite()
    {
        return ($this->empty || ($this->lower !== null && $this->upper !== null));
    }

    /**
     * @param Range|null $other a range of the same subtype as this range
     * @return bool <tt>true</tt> iff this range is strictly left of the other range, i.e., it ends before the other
     *                starts;
     *              <tt>false</tt> otherwise, especially if either range is empty;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function strictlyLeftOf($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        if ($this->empty || $other->empty) {
            return false;
        }

        if ($this->upper === null || $other->lower === null) {
            return false;
        }
        $cmp = $this->subtype->compareValues($this->upper, $other->lower);
        return ($cmp < 0 || ($cmp == 0 && (!$this->upperInc || !$other->lowerInc)));
    }

    /**
     * @param Range|null $other a range of the same subtype as this range
     * @return bool <tt>true</tt> iff this range is strictly left of the other range, i.e., it ends before the other
     *                starts;
     *              <tt>false</tt> otherwise, especially if either range is empty;;
     *              <tt>null</tt> on <tt>null</tt> input
     */
    public function strictlyRightOf($other)
    {
        if ($other === null) {
            return null;
        }
        if (!$other instanceof Range) {
            throw new \InvalidArgumentException('$other');
        }
        if ($this->empty || $other->empty) {
            return false;
        }

        if ($other->upper === null || $this->lower === null) {
            return false;
        }
        $cmp = $this->subtype->compareValues($other->upper, $this->lower);
        return ($cmp < 0 || ($cmp == 0 && (!$other->upperInc || !$this->lowerInc)));
    }

    //endregion

    //region IComparable

    public function equals($object)
    {
        if ($object === null) {
            return null;
        }
        if (!$object instanceof Range) {
            return false;
        }
        if ($this->subtype !== $object->subtype) {
            return false;
        }

        if ($this->empty) {
            return $object->empty;
        }
        if ($object->empty) {
            return false;
        }

        if ($this->lowerInc != $object->lowerInc) {
            return false;
        }
        if ($this->upperInc != $object->upperInc) {
            return false;
        }

        if ($this->lower === null) {
            if ($object->lower !== null) {
                return false;
            }
        } elseif ($this->lower instanceof IComparable) {
            if (!$this->lower->equals($object->lower)) {
                return false;
            }
        } else {
            if ($this->lower != $object->lower) {
                return false;
            }
        }

        if ($this->upper === null) {
            if ($object->upper !== null) {
                return false;
            }
        } elseif ($this->upper instanceof IComparable) {
            if (!$this->upper->equals($object->upper)) {
                return false;
            }
        } else {
            if ($this->upper != $object->upper) {
                return false;
            }
        }

        return true;
    }

    //endregion

    //region ArrayAccess

    public function offsetExists($offset)
    {
        return (!$this->empty && ($offset == 0 || $offset == 1));
    }

    public function offsetGet($offset)
    {
        if ($offset == 0) {
            return ($this->empty ? null : $this->lower);
        } elseif ($offset == 1) {
            return ($this->empty ? null : $this->upper);
        } else {
            trigger_error("Undefined range offset: $offset", E_USER_WARNING);
            return null;
        }
    }

    public function offsetSet($offset, $value)
    {
        throw new ImmutableException();
    }

    public function offsetUnset($offset)
    {
        throw new ImmutableException();
    }

    //endregion
}
