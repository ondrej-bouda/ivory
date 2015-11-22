<?php
namespace Ivory\Type;

use Ivory\NamedDbObject;

class EnumType implements INamedType
{
    use NamedDbObject;

    private $labelSet;

    public function __construct($schemaName, $typeName, $labels)
    {
        $this->setName($schemaName, $typeName);
        $this->labelSet = array_flip($labels);
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        else {
            return $str;
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        else {
            if (!isset($this->labelSet[$val])) {
                $msg = "Value '$val' is not among defined labels of enumeration type {$this->schemaName}.{$this->name}";
                trigger_error($msg, E_USER_WARNING);
            }
            return "'" . strtr($val, ["'" => "''"]) . "'";
        }
    }
}
