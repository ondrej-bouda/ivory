<?php
namespace Ivory\Relation;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Utils\ValueUtils;

/**
 * {@inheritdoc}
 *
 * This implementation is immutable, i.e., once constructed, the tuple values cannot be changed. Thus, both `__set()`
 * and `ArrayAccess` write operations (namely {@link \ArrayAccess::offsetSet()} and {@link \ArrayAccess::offsetUnset()})
 * throw an {@link \Ivory\Exception\ImmutableException}.
 */
class Tuple implements \Iterator, ITuple
{
    /** @var array list of data for the corresponding columns; already converted */
    private $data;
    /** @var string[] list of column names */
    private $columnNames;
    /** @var int[] map: column name => offset of the first column of the name */
    private $colNameMap;
    /** @var int iteration position */
    private $pos = 0;


    // TODO: allow the user to compose a tuple; it needs a relation to refer to, though
//    /**
//     * Creates a tuple from an associative array.
//     *
//     * @param array|\Traversable $map
//     * @return Tuple
//     */
//    public static function fromMap($map)
//    {
//        $data = [];
//        $columns = [];
//        $colNameMap = [];
//        foreach ($map as $k => $v) {
//            $data[] = $v;
//            $columns[] = new Column()
//            $colNameMap[$k] = count($data) - 1;
//        }
//    }

    /**
     * @param array $data list of data for the corresponding columns
     * @param string[] $columnNames list of column names
     * @param int[] $colNameMap map: column name => offset of the first column of the name
     */
    public function __construct(array $data, array $columnNames, array $colNameMap)
    {
        $this->data = $data;
        $this->columnNames = $columnNames;
        $this->colNameMap = $colNameMap;
    }


    //region ITuple

    public function toMap(): array
    {
        $res = [];
        foreach ($this->colNameMap as $name => $i) {
            $res[$name] = $this->data[$i];
        }
        return $res;
    }

    public function toList(): array
    {
        return $this->data;
    }

    public function value($colOffsetOrNameOrEvaluator = 0)
    {
        if (is_scalar($colOffsetOrNameOrEvaluator)) {
            if (filter_var($colOffsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
                if (isset($this->data[$colOffsetOrNameOrEvaluator])) {
                    return $this->data[$colOffsetOrNameOrEvaluator];
                } else {
                    throw new UndefinedColumnException("No column at offset $colOffsetOrNameOrEvaluator");
                }
            } else {
                if (isset($this->colNameMap[$colOffsetOrNameOrEvaluator])) {
                    return $this->data[$this->colNameMap[$colOffsetOrNameOrEvaluator]];
                } else {
                    throw new UndefinedColumnException("No column named $colOffsetOrNameOrEvaluator");
                }
            }
        } elseif ($colOffsetOrNameOrEvaluator instanceof ITupleEvaluator) {
            return $colOffsetOrNameOrEvaluator->evaluate($this);
        } elseif ($colOffsetOrNameOrEvaluator instanceof \Closure) {
            return call_user_func($colOffsetOrNameOrEvaluator, $this);
        } else {
            throw new \InvalidArgumentException('$colOffsetOrNameOrEvaluator');
        }
    }

    public function getColumnNames()
    {
        return $this->columnNames;
    }

    //endregion

    //region dynamic properties

    public function __get($name)
    {
        if (isset($this->colNameMap[$name])) {
            return $this->data[$this->colNameMap[$name]];
        } else {
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
            return array_key_exists($offset, $this->data);
        } else {
            return isset($this->colNameMap[$offset]);
        }
    }

    public function offsetGet($offset)
    {
        if (filter_var($offset, FILTER_VALIDATE_INT) !== false) {
            $key = $offset;
        } else {
            $key = $this->colNameMap[$offset];
        }

        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        } else {
            trigger_error("Undefined offset `$offset` for the tuple");
            return null;
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

    //region IComparable

    public function equals($object)
    {
        if (!$object instanceof ITuple) {
            return false;
        }
        return ValueUtils::equals($this->data, $object->toList());
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
        return $this->columnNames[$this->pos];
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
