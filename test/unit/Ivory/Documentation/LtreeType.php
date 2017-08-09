<?php
declare(strict_types=1);
namespace Ivory\Documentation;

class LtreeType implements \Ivory\Type\IType
{
    public function getSchemaName(): string
    {
        return 'public';
    }

    public function getName(): string
    {
        return 'ltree';
    }

    public function parseValue(string $extRepr)
    {
        $labels = explode('.', $extRepr);
        return Ltree::fromArray($labels);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return ($strictType ? 'NULL::ltree' : 'NULL');
        }

        if ($val instanceof Ltree) {
            $ltree = $val;
        } elseif (is_array($val)) {
            $ltree = Ltree::fromArray($val);
        } else {
            throw new \InvalidArgumentException('Invalid ltree value');
        }

        return ($strictType ? 'ltree ' : '') . "'" . implode('.', $ltree->toArray()) . "'";
    }
}
