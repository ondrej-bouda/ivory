<?php
namespace Ivory\Data\Set;

/**
 * {@inheritdoc}
 *
 * This implementation:
 * - uses the PHP array type to store the data;
 * - stores `int`s and `string`s as is, `serialize`()-ing other types of data.
 */
class DictionarySet implements ISet
{
    private $data = [];

    protected function computeKey($value)
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        } else {
            return serialize($value);
        }
    }

    //region ISet

    public function contains($value)
    {
        $key = $this->computeKey($value);
        return isset($this->data[$key]);
    }

    public function add($value)
    {
        $key = $this->computeKey($value);
        $this->data[$key] = true;
    }

    public function remove($value)
    {
        $key = $this->computeKey($value);
        unset($this->data[$key]);
    }

    //endregion

    //region \Countable

    public function count()
    {
        return count($this->data);
    }

    //endregion
}
