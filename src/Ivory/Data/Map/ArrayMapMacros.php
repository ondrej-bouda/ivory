<?php
namespace Ivory\Data\Map;

// Until PHP has generics, there will have to be separate interfaces for maps storing different types of objects.
trait ArrayMapMacros
{
    private $map = [];


    abstract protected function isNestedMap($entry);

    //region map operations

    public function get(...$key)
    {
        $data = $this;
        $className = __CLASS__;
        foreach ($key as $i => $k) {
            if (!$this->isNestedMap($data)) {
                throw new \InvalidArgumentException("Invalid key `$k`");
            }
            if (!$data instanceof $className) {
                return $data->get(...array_slice($key, $i));
            }
            if (!isset($data->map[$k])) {
                throw new \OutOfBoundsException($k);
            }
            $data = $data->map[$k];
        }
        return $data;
    }

    public function maybe(...$key)
    {
        $data = $this;
        $className = __CLASS__;
        foreach ($key as $i => $k) {
            if (!$this->isNestedMap($data)) {
                throw new \InvalidArgumentException("Invalid key `$k`");
            }
            if (!$data instanceof $className) {
                return $data->maybe(...array_slice($key, $i));
            }
            if (!isset($data->map[$k])) {
                return null;
            }
            $data = $data->map[$k];
        }
        return $data;
    }

    public function put($key, $entry)
    {
        $this->map[$key] = $entry;
    }

    public function putIfNotExists($key, $entry)
    {
        if (isset($this->map[$key])) {
            return false;
        }
        $this->map[$key] = $entry;
        return true;
    }

    public function remove(...$key)
    {
        if (!$key) {
            throw new \InvalidArgumentException('empty $key');
        }

        $k = $key[0];
        if (!isset($this->map[$k])) {
            return false;
        }
        if (count($key) == 1) {
            unset($this->map[$k]);
            return true;
        } else {
            $inner = $this->map[$k];
            if (!$this->isNestedMap($inner)) {
                throw new \InvalidArgumentException("Invalid key `$k`");
            }
            return $inner->remove(...array_slice($key, 1));
        }
    }

    public function getKeys()
    {
        return array_keys($this->map);
    }

    public function count()
    {
        return count($this->map);
    }

    public function offsetExists($offset)
    {
        return ($this->maybe($offset) !== null);
    }

    public function offsetGet($offset)
    {
        return $this->maybe($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->put($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    //endregion

    //region IteratorAggregate

    public function getIterator()
    {
        return new \ArrayIterator($this->map);
    }

    //endregion
}
