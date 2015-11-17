<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;
use Ivory\Type\IType;

class Column implements IColumn
{
    private $name;
    private $type;


    /**
     * @param string|null $name name of the column, or <tt>null</tt> if not named
     * @param IType $type type of the column values
     */
    public function __construct($name, IType $type)
    {
        $this->name = $name;
        $this->type = $type;
    }


    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function filter($decider)
    {
        throw new NotImplementedException();
    }

    public function uniq($hasher = null, $comparator = null)
    {
        throw new NotImplementedException();
    }

    public function toArray()
    {
        throw new NotImplementedException();
    }

    public function value($valueOffset = 0)
    {
        throw new NotImplementedException();
    }

    //region ICachingDataProcessor

    public function populate()
    {
        throw new NotImplementedException();
    }

    public function flush()
    {
        throw new NotImplementedException();
    }

    //endregion


    //region Countable

    public function count()
    {
        throw new NotImplementedException();
    }

    //endregion
}
