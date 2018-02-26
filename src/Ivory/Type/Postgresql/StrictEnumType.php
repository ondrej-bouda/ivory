<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Lang\Sql\Types;
use Ivory\NamedDbObject;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\StrictEnum;

class StrictEnumType implements ITotallyOrderedType
{
    use NamedDbObject;

    private $enumClass;

    public function __construct(string $schemaName, string $typeName, string $enumClass)
    {
        assert(is_subclass_of($enumClass, StrictEnum::class));
        $this->setName($schemaName, $typeName);
        $this->enumClass = $enumClass;
    }

    public function parseValue(string $extRepr)
    {
        return new $this->enumClass($extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof StrictEnum) {
            throw new \InvalidArgumentException('Instance of ' . StrictEnum::class . ' is expected');
        }
        if (!is_a($val, $this->enumClass)) {
            throw new \InvalidArgumentException('Serializing enumeration value for a different type');
        }

        return sprintf(
            '%s::%s.%s',
            Types::serializeString($val->getValue()),
            Types::serializeIdent($this->getSchemaName()),
            Types::serializeIdent($this->getName())
        );
    }
}
