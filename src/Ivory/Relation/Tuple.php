<?php
namespace Ivory\Relation;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;

/**
 * {@inheritdoc}
 *
 * This implementation is immutable, i.e., once constructed, the tuple values cannot be changed. Thus, both `__set()`
 * and `ArrayAccess` write operations (namely {@link \ArrayAccess::offsetSet()} and {@link \ArrayAccess::offsetUnset()})
 * throw an {@link \Ivory\Exception\UnsupportedException}.
 */
class Tuple implements \Iterator, ITuple
{
    /** @var array list of data for the corresponding columns; already converted */
    private $data;
    /** @var Column[] list of columns */
    private $columns;
    /** @var int[] map: column name => offset of the first column of the name */
    private $colNameMap;
    /** @var int iteration position */
    private $pos = 0;

    /**
     * @param array $data
     * @param Column[] $columns
     * @param \int[] $colNameMap
     */
    public function __construct(array $data, array $columns, array $colNameMap)
    {
        $this->data = $data;
        $this->columns = $columns;
        $this->colNameMap = $colNameMap;
    }


    //region ITuple

    public function toMap()
    {
        // TODO: benchmark which is faster, perhaps with some expressions precomputed and passed to the constructor...
//        return array_combine(array_keys($this->colNameMap), array_intersect_key($this->data, $this->colNameMap));

        $res = [];
        foreach ($this->colNameMap as $name => $i) {
            $res[$name] = $this->data[$i];
        }
        return $res;
    }

    public function toList()
    {
        return $this->data;
    }

    public function value($colOffsetOrNameOrEvaluator = 0)
    {
        if (is_scalar($colOffsetOrNameOrEvaluator)) {
            if (filter_var($colOffsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
                return $this->data[$colOffsetOrNameOrEvaluator];
            }
            elseif (isset($this->colNameMap[$colOffsetOrNameOrEvaluator])) {
                return $this->data[$this->colNameMap[$colOffsetOrNameOrEvaluator]];
            }
            else {
                throw new UndefinedColumnException($colOffsetOrNameOrEvaluator);
            }
        }
        elseif ($colOffsetOrNameOrEvaluator instanceof ITupleEvaluator) {
            return $colOffsetOrNameOrEvaluator->evaluate($this);
        }
        elseif ($colOffsetOrNameOrEvaluator instanceof \Closure) {
            return call_user_func($colOffsetOrNameOrEvaluator, $this);
        }
        else {
            throw new \InvalidArgumentException('$colOffsetOrNameOrEvaluator');
        }
    }

    //endregion

    //region dynamic properties

    public function __get($name)
    {
        if (isset($this->colNameMap[$name])) {
            return $this->data[$this->colNameMap[$name]];
        }
        else {
            return null;
        }
    }

    public function __isset($name)
    {
        return isset($this->colNameMap[$name]);
    }

    //endregion

    //region \ArrayAccess

    public function offsetExists($offset)
    {
        if (filter_var($offset, FILTER_VALIDATE_INT) !== false) {
            return isset($this->data[$offset]);
        }
        else {
            return isset($this->colNameMap[$offset]);
        }
    }

    public function offsetGet($offset)
    {
        if (filter_var($offset, FILTER_VALIDATE_INT) !== false) {
            $key = $offset;
        }
        else {
            $key = $this->colNameMap[$offset];
        }

        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        else {
            trigger_error("Undefined offset `$offset` for the tuple");
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

    //region \Iterator

    public function current()
    {
        return $this->data[$this->pos];
    }

    public function next()
    {
        $this->pos++;
    }

    public function key()
    {
        return $this->columns[$this->pos]->getName();
    }

    public function valid()
    {
        return ($this->pos < count($this->data));
    }

    public function rewind()
    {
        $this->pos = 0;
    }

    //endregion
}
