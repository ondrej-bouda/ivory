<?php
namespace Ivory\Type\Std;

use Ivory\Value\Json;

/**
 * Exactly represented JSON-encoded data type.
 *
 * Represented as a {@link \Ivory\Value\Json} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-json.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class JsonExactType extends JsonType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        try {
            return Json::fromEncoded($str);
        } catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
        }
    }
}
