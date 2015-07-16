<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;

/**
 * JSON-encoded data type.
 *
 * Represented as the PHP <tt>object|array|string|int|float|bool</tt> type - the JSON content gets unpacked for PHP.
 *
 * Note that, to distinguish the <tt>null</tt> value in the JSON-encoded data from SQL <tt>NULL</tt>, the former is not
 * represented by the PHP <tt>null</tt> value but rather using a special <tt>\Closure</tt> object retrieved by the
 * {@link JsonType::jsonNull()} method. Use the identity operator <tt>===</tt> for distinguishing it from real objects.
 */
class JsonType extends BaseType
{
    /**
     * Returns the special object representing the JSON-encoded <tt>null</tt> value.
     *
     * The same object is always returned.
     * The only thing to do with the return object is to compare it, using the identity operator <tt>===</tt>, with
     * other values, e.g., parsed from a result set.
     *
     * @return \Closure
     */
    public static function jsonNull()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = function () {
                throw new \LogicException(
                    'This closure just represents the JSON-encoded null value. It shall only be compared with other values using the identity operator (===).'
                );
            };
        }
        return $inst;
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        $val = json_decode($str);
        if ($val === null) {
            if (json_last_error() == JSON_ERROR_NONE) {
                return self::jsonNull(); // a legal null value decoded from the JSON
            }
            else {
                $this->handleJsonError();
            }
        }

        return $val;
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if ($val === self::jsonNull()) {
            $val = null;
        }
        $json = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->handleJsonError();
        }

        return "'" . strtr($json, ["'" => "''"]) . "'";
    }

    private function handleJsonError()
    {
        if (PHP_VERSION_ID >= 50500) {
            $msg = json_last_error_msg();
        }
        else {
            static $messages = [
                JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
                JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
                JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
                JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
                JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            ];
            $error = json_last_error();
            $msg = (isset($messages[$error]) ? $messages[$error] : "Unknown JSON error ($error)");
        }

        $this->throwInvalidValue($msg);
    }
}
