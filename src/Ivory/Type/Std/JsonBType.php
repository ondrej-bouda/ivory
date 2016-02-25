<?php
namespace Ivory\Type\Std;

use Ivory\Value\Json;

/**
 * JSON-encoded data type.
 *
 * Represented as the PHP `\stdClass|array|string|int|float|bool` type - the JSON content gets unpacked for PHP.
 *
 * Note that, to distinguish the `null` value in the JSON-encoded data from SQL `NULL`, the former is not represented by
 * the PHP `null` value but rather by the object returned by {@link \Ivory\Value\Json::null()}. This is the only case
 * when a {@link \Ivory\Value\Json} object is returned from the database. In other cases, the unpacked value, encoded by
 * the JSON, is returned. You can use `($value === \Ivory\Value\Json::null())` to distinguish the JSON-encoded `null`
 * value.
 *
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class JsonBType extends JsonType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        try {
            $json = Json::fromEncoded($str);
            return ($json->getValue() === null ? Json::null() : $json->getValue());
        }
        catch (\InvalidArgumentException $e) {
            $this->throwInvalidValue($str, $e);
        }
    }
}
