<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\AmbiguousException;
use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Value\Alg\ITupleEvaluator;
use Ivory\Value\Alg\ComparisonUtils;

/**
 * Represents one relation row.
 *
 * {@inheritDoc}
 *
 * This implementation is immutable, i.e., once constructed, the tuple values cannot be changed. Thus, both `__set()`
 * and `ArrayAccess` write operations (namely {@link \ArrayAccess::offsetSet()} and {@link \ArrayAccess::offsetUnset()})
 * throw an {@link \Ivory\Exception\ImmutableException}.
 */
class Tuple implements ITuple
{
    const AMBIGUOUS_COL = false;

    /** @var array list of data for the corresponding columns; already converted */
    private $data;
    /** @var int[] map: column name => offset of the first column of the name */
    private $colNameMap;


    /**
     * Creates a tuple from a key => value map.
     *
     * @param iterable $map
     * @return Tuple
     */
    public static function fromMap(iterable $map): Tuple
    {
        $data = [];
        $colNameMap = [];
        foreach ($map as $k => $v) {
            $data[] = $v;
            $colNameMap[$k] = count($data) - 1;
        }
        return new Tuple($data, $colNameMap);
    }

    /**
     * @param array $data list of data for the corresponding columns
     * @param array $colNameMap map: column name => zero-based offset of the column of the given name, or
     *                            <tt>Tuple::AMBIGUOUS_COL</tt> for denoting the name of the column is used multiple
     *                            times within the originating relation
     */
    public function __construct(array $data, array $colNameMap)
    {
        $this->data = $data;
        $this->colNameMap = $colNameMap;
    }


    //region ITuple

    public function toMap(): array
    {
        $res = [];
        foreach ($this->colNameMap as $name => $i) {
            if ($i === self::AMBIGUOUS_COL) {
                throw new AmbiguousException(
                    "There is an ambiguous column `$name`, preventing the tuple to be converted to a map"
                );
            }
            $res[$name] = $this->data[$i];
        }
        return $res;
    }

    public function toList(): array
    {
        return $this->data;
    }

    public function value($colOffsetOrNameOrEvaluator)
    {
        if (is_scalar($colOffsetOrNameOrEvaluator)) {
            if (filter_var($colOffsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
                return $this[$colOffsetOrNameOrEvaluator];
            } else {
                return $this->{$colOffsetOrNameOrEvaluator};
            }
        } elseif ($colOffsetOrNameOrEvaluator instanceof ITupleEvaluator) {
            return $colOffsetOrNameOrEvaluator->evaluate($this);
        } elseif ($colOffsetOrNameOrEvaluator instanceof \Closure) {
            return call_user_func($colOffsetOrNameOrEvaluator, $this);
        } else {
            throw new \InvalidArgumentException('$colOffsetOrNameOrEvaluator');
        }
    }

    //endregion

    //region dynamic properties

    public function __get(string $name)
    {
        if (!isset($this->colNameMap[$name])) {
            throw new UndefinedColumnException("No column named $name");
        } elseif ($this->colNameMap[$name] === self::AMBIGUOUS_COL) {
            throw new AmbiguousException("There are multiple columns named `$name` in the tuple");
        } else {
            return $this->data[$this->colNameMap[$name]];
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->colNameMap[$name]);
    }

    public function __set($name, $value)
    {
        throw new ImmutableException();
    }

    public function __unset($name)
    {
        throw new ImmutableException();
    }

    //endregion

    //region \ArrayAccess

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        } else {
            throw new UndefinedColumnException("There is no column at offset `$offset` in the tuple");
        }
    }

    final public function offsetSet($offset, $value)
    {
        throw new ImmutableException();
    }

    final public function offsetUnset($offset)
    {
        throw new ImmutableException();
    }

    //endregion

    //region IEqualable

    public function equals($other): bool
    {
        if (!$other instanceof ITuple) {
            return false;
        }
        return ComparisonUtils::equals($this->data, $other->toList());
    }

    //endregion
}
