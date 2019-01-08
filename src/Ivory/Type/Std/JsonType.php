<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Value\Json;

/**
 * Base for JSON types.
 *
 * @see https://www.postgresql.org/docs/11/datatype-json.html
 */
abstract class JsonType extends TypeBase
{
    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof Json) {
            try {
                $val = Json::fromValue($val);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($val, $e);
            }
        }

        return $this->indicateType($forceType, Types::serializeString($val->getEncoded()));
    }
}
