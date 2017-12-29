<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Encapsulation of JSON-encoded data.
 *
 * The meaning of this class is to represent JSON-encoded data including the semantically insignificant whitespace among
 * tokens, as well as the order of object keys and multiple occurrences of the same key (when working with the actual
 * value, the last occurrence of each key is actually used). This reflects the PostgreSQL `JSON` data type. In order to
 * do that, both the value and its JSON encoding are kept in the object.
 *
 * The objects are immutable, i.e., operations always produce a new object.
 */
class Json
{
    private $value;
    private $encoded;


    /**
     * @param mixed $value value to be represented
     * @return Json
     */
    public static function fromValue($value): Json
    {
        $json = self::jsonEncode($value);
        return new Json($value, $json);
    }

    /**
     * @param string $json JSON-encoded value
     * @return Json
     */
    public static function fromEncoded(string $json): Json
    {
        $value = self::jsonDecode($json);
        return new Json($value, $json);
    }

    /**
     * @return Json representation of the <tt>null</tt> value; always returns the same object
     */
    public static function null(): Json
    {
        static $inst = null;
        if ($inst === null) {
            $inst = self::fromValue(null);
        }
        return $inst;
    }

    private function __construct($value, string $encoded)
    {
        $this->value = $value;
        $this->encoded = $encoded;
    }

    /**
     * @return bool|int|float|string|array|\stdClass the represented value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string the JSON encoding of the represented value
     */
    public function getEncoded(): string
    {
        return $this->encoded;
    }

    private static function jsonEncode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        return $json;
    }

    private static function jsonDecode(string $json)
    {
        $value = json_decode($json);
        if ($value === null && json_last_error() != JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        return $value;
    }
}
