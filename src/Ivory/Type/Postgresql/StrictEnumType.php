<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Lang\Sql\Types;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TypeBase;
use Ivory\Value\StrictEnum;

// TODO: document the intended usage - see EnumType class docs
class StrictEnumType extends TypeBase implements ITotallyOrderedType
{
    private $enumClass;

    public function __construct(string $schemaName, string $typeName, string $enumClass)
    {
        parent::__construct($schemaName, $typeName);
        assert(is_subclass_of($enumClass, StrictEnum::class));
        $this->enumClass = $enumClass;
    }

    public function parseValue(string $extRepr)
    {
        return new $this->enumClass($extRepr);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof StrictEnum) {
            throw new \InvalidArgumentException('Instance of ' . StrictEnum::class . ' is expected');
        }
        if (!is_a($val, $this->enumClass)) {
            throw new \InvalidArgumentException('Serializing enumeration value for a different type');
        }

        return $this->indicateType($strictType, Types::serializeString($val->getValue()));
    }
}
