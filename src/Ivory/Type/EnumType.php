<?php
namespace Ivory\Type;

use Ivory\Exception\IncomparableException;
use Ivory\NamedDbObject;

class EnumType implements INamedType, ITotallyOrderedType
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
        } else {
            return $str;
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        } else {
            if (!isset($this->labelSet[$val])) {
                $msg = "Value '$val' is not among defined labels of enumeration type {$this->schemaName}.{$this->name}";
                trigger_error($msg, E_USER_WARNING);
            }
            return "'" . strtr($val, ["'" => "''"]) . "'";
        }
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }
        if (!isset($this->labelSet[$a]) || !isset($this->labelSet[$b])) {
            throw new IncomparableException('Incompatible enums');
        }
        return $this->labelSet[$a] - $this->labelSet[$b];
    }
}
