<?php
declare(strict_types=1);
namespace Ivory\Relation;

class ColumnIterator implements \SeekableIterator
{
    private $column;
    private $pos = 0;

    public function __construct(IColumn $column)
    {
        $this->column = $column;
    }

    public function current()
    {
        return $this->column->value($this->pos);
    }

    public function next(): void
    {
        $this->pos++;
    }

    public function key(): int
    {
        return $this->pos;
    }

    public function valid(): bool
    {
        return ($this->pos < $this->column->count());
    }

    public function rewind(): void
    {
        $this->pos = 0;
    }

    public function seek($offset): void
    {
        $this->pos = $offset;
    }
}
