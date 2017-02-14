<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\Json;

/**
 * Base for JSON types.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-json.html
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
                $this->throwInvalidValue($val, $e);
            }
        }

        $encoded = $val->getEncoded();
        return "'" . strtr($encoded, ["'" => "''"]) . "'";
    }
}
