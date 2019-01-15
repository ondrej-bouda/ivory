<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Type\IType;
use Ivory\Value\Composite;

/**
 * A composite type is basically a tuple of values.
 *
 * A composite type has several attributes, each given by its name, type, and position.
 */
class CompositeType extends RowTypeBase
{
    /** @var IType[] list: attribute position within the composite type => attribute type */
    private $attTypes = [];
    /** @var int[] map: attribute name => position of the attribute within the composite type */
    private $attNameMap = [];

    /**
     * Defines a new attribute of this composite type.
     *
     * @param string $attName
     * @param IType $attType
     * @return $this
     */
    public function addAttribute(string $attName, IType $attType): self
    {
        if ((string)$attName == '') {
            $typeName = "{$this->getSchemaName()}.{$this->getName()}";
            $msg = "No attribute name given when adding attribute to composite type $typeName";
            throw new \InvalidArgumentException($msg);
        }
        if (isset($this->attNameMap[$attName])) {
            $typeName = "{$this->getSchemaName()}.{$this->getName()}";
            throw new \RuntimeException("Attribute '$attName' already defined on composite type $typeName");
        }

        $this->attTypes[] = $attType;
        $this->attNameMap[$attName] = count($this->attNameMap);

        return $this;
    }

    protected function parseItem(int $pos, string $itemExtRepr)
    {
        return $this->attTypes[$pos]->parseValue($itemExtRepr);
    }

    protected function makeParsedValue(array $items)
    {
        $valueMap = [];
        foreach ($this->attNameMap as $name => $pos) {
            $valueMap[$name] = ($items[$pos] ?? null);
        }
        return $this->constructCompositeValue($valueMap);
    }

    protected function constructCompositeValue(array $valueMap): Composite
    {
        return Composite::fromMap($valueMap);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            $expr = 'NULL';
        } else {
            $expr = $this->makeRowExpression($val, $strictType);
        }
        return $this->typeCastExpr($strictType, $expr);
    }

    protected function serializeBody(string &$result, $value, bool $strictType = true): int
    {
        if (is_array($value)) {
            $value = Composite::fromMap($value);
        } elseif (!$value instanceof Composite) {
            throw $this->invalidValueException($value);
        }

        $cnt = 0;
        foreach ($this->attNameMap as $name => $pos) {
            if ($cnt > 0) {
                $result .= ',';
            }
            $type = $this->attTypes[$pos];
            $result .= $type->serializeValue(($value->{$name} ?? null), $strictType);
            $cnt++;
        }
        return $cnt;
    }
}
