<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Lang\Sql\Types;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TypeBase;
use Ivory\Value\EnumItem;

/**
 * Generic enumeration type converter.
 *
 * When parsing enumeration values from PostgreSQL, objects of {@link EnumItem} are produced, which hold both the type
 * name and the order of the value within the enumeration values (recall that in PostgreSQL, enumeration values are
 * compared based on their position within the defined enum). The `EnumItem` objects are comparable, and even ranges of
 * enum values (of the same enumeration type) may be constructed.
 *
 * The generality of the `EnumItem` objects comes at a cost, however: to construct an enumeration value, one must use
 * the {@link EnumItem::forType()} factory method and give it, besides the actual value, also the schema name, the type
 * name and the ordinal. To remedy this problem, plain strings are also accepted by this type converter when serializing
 * a value to PostgreSQL; still, the given string is checked whether among the values defined by the enumeration, and a
 * warning is raised if not. Also note the {@link EnumItem::getValue()} method, returning the enum value as a string,
 * which is also returned when simply typecasting an `EnumItem` to a string.
 *
 * For a stricter and, actually, a more comfortable solution, subclass {@link StrictEnum} for each PostgreSQL
 * enumeration type and use the class in {@link StrictEnumType}.
 */
class EnumType extends TypeBase implements ITotallyOrderedType
{
    private $labelSet;

    public function __construct(string $schemaName, string $typeName, array $labels)
    {
        parent::__construct($schemaName, $typeName);
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

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if ($val instanceof EnumItem) {
            $schemaName = $this->getSchemaName();
            $enumName = $this->getName();
            if ($val->getTypeSchema() != $schemaName || $val->getTypeName() != $enumName) {
                $msg = "Serializing enum item for a different type $schemaName.$enumName";
                trigger_error($msg, E_USER_WARNING);
            }
            $v = $val->getValue();
        } else {
            if (!isset($this->labelSet[$val])) {
                $schemaName = $this->getSchemaName();
                $enumName = $this->getName();
                $msg = "Value '$val' is not among defined labels of enumeration type $schemaName.$enumName";
                trigger_error($msg, E_USER_WARNING);
            }
            $v = $val;
        }

        return $this->indicateType($strictType, Types::serializeString($v));
    }
}
