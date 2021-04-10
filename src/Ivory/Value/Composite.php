<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\IComparable;
use Ivory\Value\Alg\ComparisonUtils;

/**
 * A composite value of several attributes.
 *
 * The attribute values are accessible as dynamic properties. E.g., attribute "foo" is accessed using `$val->foo`.
 * Besides, the composite value is `Traversable` - its attribute names and values are returned during traversal.
 *
 * Note the composite value is immutable, i.e., once constructed, its values cannot be changed.
 *
 * _Ivory design note: Although Composite seems similar to Tuple and using the same class for both might look
 * reasonable, there is a significant difference: in Composite, each attribute name is unique, whereas in Tuple,
 * multiple columns might have a same name. Moreover, it is customary to access tuples with array indices, while
 * composite attributes should only be accessed through attribute names. Hence, two different classes._
 *
 * @see https://www.postgresql.org/docs/11/rowtypes.html
 */
class Composite implements IComparable, \IteratorAggregate
{
    /** @var array map: attribute name => value */
    private $values;

    /**
     * Creates a new composite value out of a map of attribute names to the corresponding values.
     *
     * Attributes not mentioned in the given map will be considered as `null`.
     *
     * @param array $valueMap map: attribute name => value; unspecified attributes get a <tt>null</tt> value
     * @return Composite
     */
    public static function fromMap(array $valueMap): Composite
    {
        return new Composite($valueMap);
    }

    protected function __construct(array $valueMap)
    {
        $this->values = $valueMap;
    }

    /**
     * @return array ordered map: attribute name => attribute value
     */
    public function toMap(): array
    {
        return $this->values;
    }

    //region dynamic properties

    /**
     * @param string $name attribute name
     * @return mixed value of the given attribute, or <tt>null</tt> if no such attribute is defined on this value
     */
    public function __get(string $name)
    {
        return ($this->values[$name] ?? null);
    }

    /**
     * @param string $name attribute name
     * @return bool whether the attribute is defined and non-null on this value
     */
    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    //endregion

    //region IComparable

    public function equals($other): bool
    {
        if (!$other instanceof Composite) {
            return false;
        }

        $otherValues = $other->values;

        if (count($this->values) != count($otherValues)) {
            return false;
        }

        foreach ($this->values as $name => $thisVal) {
            if (!ComparisonUtils::equals($thisVal, ($otherValues[$name] ?? null))) {
                return false;
            }
        }

        return true;
    }

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException();
        }
        if (!$other instanceof Composite) {
            throw new IncomparableException('$other is not a ' . Composite::class);
        }

        $otherValues = $other->values;

        if (count($this->values) != count($otherValues)) {
            throw new IncomparableException('$other contains different attributes');
        }

        foreach ($this->values as $name => $thisVal) {
            $otherVal = ($otherValues[$name] ?? null);
            $cmp = ComparisonUtils::compareValues($thisVal, $otherVal);
            if ($cmp != 0) {
                return $cmp;
            }
        }

        return 0;
    }

    //endregion

    //region IteratorAggregate

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->values);
    }

    //endregion
}
