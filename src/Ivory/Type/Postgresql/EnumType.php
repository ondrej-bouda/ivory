<?php
namespace Ivory\Type\Postgresql;

use Ivory\Exception\IncomparableException;
use Ivory\NamedDbObject;
use Ivory\Type\ITotallyOrderedType;

class EnumType implements ITotallyOrderedType
{
    use NamedDbObject;

    private $labelSet;

    public function __construct(string $schemaName, string $typeName, $labels)
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

    public function serializeValue($val): string
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
