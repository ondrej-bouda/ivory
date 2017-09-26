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

    public function next()
    {
        $this->pos++;
    }

    public function key()
    {
        return $this->pos;
    }

    public function valid()
    {
        return ($this->pos < $this->column->count());
    }

    public function rewind()
    {
        return $this->pos = 0;
    }

    public function seek($position)
    {
        $this->pos = $position;
    }
}
