<?php
namespace Ivory\Type\Std;

use Ivory\Value\Json;

/**
 * JSON-encoded data type.
 *
 * Represented as the PHP <tt>\stdClass|array|string|int|float|bool</tt> type - the JSON content gets unpacked for PHP.
 *
 * Note that, to distinguish the <tt>null</tt> value in the JSON-encoded data from SQL <tt>NULL</tt>, the former is not
 * represented by the PHP <tt>null</tt> value but rather by the object returned by {@link \Ivory\Value\Json::null()}.
 * This is the only case when a {@link \Ivory\Value\Json} object is returned from the database. In other cases, the
 * unpacked value, encoded by the JSON, is returned. You can use <tt>($value === \Ivory\Value\Json::null())</tt> to
 * distinguish the JSON-encoded <tt>null</tt> value.
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
