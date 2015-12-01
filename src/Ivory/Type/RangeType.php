<?php
namespace Ivory\Type;

use Ivory\Exception\NotImplementedException;
use Ivory\NamedDbObject;

class RangeType implements INamedType
{
    use NamedDbObject;

    private $subtype;

    public function __construct($schemaName, $name, IType $subtype)
    {
        $this->setName($schemaName, $name);
        $this->subtype = $subtype;
    }

    public function parseValue($str)
    {
        throw new NotImplementedException(); // TODO
    }

    public function serializeValue($val)
    {
        throw new NotImplementedException(); // TODO
    }
}
