<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Data\Set;

/**
 * An array-based set of values.
 *
 * {@inheritDoc}
 *
 * This implementation:
 * - uses the PHP array type to store the data;
 * - stores `int`s as is, `serialize`()-ing other types of data.
 */
class DictionarySet implements ISet
{
    private $data = [];

    protected function computeKey($value)
    {
        if (is_int($value)) {
            return $value;
        } else {
            return serialize($value);
        }
    }

    //region ISet

    public function contains($value): bool
    {
        $key = $this->computeKey($value);
        return isset($this->data[$key]);
    }

    public function add($value): void
    {
        $key = $this->computeKey($value);
        $this->data[$key] = $value;
    }

    public function remove($value): void
    {
        $key = $this->computeKey($value);
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function toArray(): array
    {
        return array_values($this->data);
    }

    public function generateItems(): \Generator
    {
        foreach ($this->data as $item) {
            yield $item;
        }
    }

    //endregion

    //region \Countable

    public function count()
    {
        return count($this->data);
    }

    //endregion
}
