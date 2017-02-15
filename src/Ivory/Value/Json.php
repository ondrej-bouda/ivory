<?php
namespace Ivory\Value;

/**
 * Encapsulation of JSON-encoded data.
 *
 * The meaning of this class is to represent JSON-encoded data including the semantically insignificant whitespace among
 * tokens, as well as the order of object keys and multiple occurrences of the same key (the last of which is actually
 * used when working with the actual value). This reflects the PostgreSQL `JSON` data type. In order to do that, both
 * the value and its JSON encoding are kept in the object.
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
            throw new \InvalidArgumentException(self::getJsonErrorMsg());
        }
        return $json;
    }

    private static function jsonDecode(string $json)
    {
        $value = json_decode($json);
        if ($value === null && json_last_error() != JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(self::getJsonErrorMsg());
        }
        return $value;
    }

    private static function getJsonErrorMsg()
    {
        if (PHP_VERSION_ID >= 50500) {
            return json_last_error_msg();
        } else {
            static $messages = [
                JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
                JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
                JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
                JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
                JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            ];
            $error = json_last_error();
            return (isset($messages[$error]) ? $messages[$error] : "Unknown JSON error ($error)");
        }
    }
}
