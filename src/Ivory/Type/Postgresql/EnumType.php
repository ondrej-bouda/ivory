<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Exception\IncomparableException;
use Ivory\Lang\Sql\Types;
use Ivory\NamedDbObject;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\EnumItem;

class EnumType implements ITotallyOrderedType
{
    use NamedDbObject;

    private $labelSet;

    public function __construct(string $schemaName, string $typeName, $labels)
    {
        $this->setName($schemaName, $typeName);
        $this->labelSet = array_flip($labels);
    }

    public function parseValue(string $extRepr)
    {
        return EnumItem::forType(
            $this->getSchemaName(),
            $this->getName(),
            $extRepr,
            ($this->labelSet[$extRepr] ?? null)
        );
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if ($val instanceof EnumItem) {
            if ($val->getTypeSchema() != $this->getSchemaName() || $val->getTypeName() != $this->getName()) {
                $msg = "Serializing enum item for a different type {$this->schemaName}.{$this->name}";
                trigger_error($msg, E_USER_WARNING);
            }
            $v = $val->getValue();
        } else {
            if (!isset($this->labelSet[$val])) {
                $msg = "Value '$val' is not among defined labels of enumeration type {$this->schemaName}.{$this->name}";
                trigger_error($msg, E_USER_WARNING);
            }
            $v = $val;
        }

        return sprintf(
            '%s::%s.%s',
            Types::serializeString($v),
            Types::serializeIdent($this->getSchemaName()),
            Types::serializeIdent($this->getName())
        );
    }

    public function compareValues($a, $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        if (!$a instanceof EnumItem || !$b instanceof EnumItem) {
            throw new IncomparableException();
        }
        return $a->compareTo($b);
    }
}
