<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Json;

/**
 * Exactly represented JSON-encoded data type.
 *
 * Represented as a {@link \Ivory\Value\Json} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-json.html
 */
class JsonExactType extends JsonType
{
    public function parseValue(string $extRepr)
    {
        try {
            return Json::fromEncoded($extRepr);
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($extRepr, $e);
        }
    }
}
