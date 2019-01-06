<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\BaseType;
use Ivory\Value\Json;

/**
 * Base for JSON types.
 *
 * @see https://www.postgresql.org/docs/11/datatype-json.html
 */
abstract class JsonType extends BaseType
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Json) {
            try {
                $val = Json::fromValue($val);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($val, $e);
            }
        }

        return sprintf(
            '%s.%s %s',
            Types::serializeIdent($this->getSchemaName()),
            Types::serializeIdent($this->getName()),
            Types::serializeString($val->getEncoded())
        );
    }
}
