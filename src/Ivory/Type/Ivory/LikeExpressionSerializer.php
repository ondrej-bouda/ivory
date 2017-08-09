<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Lang\Sql\Types;
use Ivory\Type\IValueSerializer;

/**
 * Serializer for `LIKE` expressions.
 *
 * Escapes a string to be safely used as the `LIKE` right operand. Special characters are treated appropriately not to
 * have their special meaning for `LIKE`.
 *
 * Note the escape character, used for treating the special characters, may be specified in `LIKE` expressions:
 * `string LIKE pattern [ESCAPE escape-character]`. The serializer assumes this feature is not used, i.e., the default
 * backslash character is used for escaping.
 *
 * Depending on the mode this serializer is constructed in, the string is prefixed or postfixed with the `%` wildcard so
 * that it matches data ending or starting with the given string.
 *
 * @see https://www.postgresql.org/docs/11/functions-matching.html
 */
class LikeExpressionSerializer implements IValueSerializer
{
    /** Do not prepend or append wildcards - match the string as is. */
    const WILDCARD_NONE = 0;
    /** Prepend the wildcard to the string - match data ending with the given string. */
    const WILDCARD_PREPEND = 1;
    /** Append the wildcard to the string - match data starting with the given string. */
    const WILDCARD_APPEND = 2;
    /** Both prepend and append wildcards to the string - match data containing the given string. */
    const WILDCARD_BOTH = 3;

    const ESCAPE_CHAR = '\\';

    /** @var string pattern to prepend to the string to escape */
    private $prefix;
    /** @var string pattern to append to the string to escape */
    private $postfix;

    public function __construct(int $wildcard = self::WILDCARD_NONE)
    {
        $this->prefix = (($wildcard & self::WILDCARD_PREPEND) != 0 ? '%' : '');
        $this->postfix = (($wildcard & self::WILDCARD_APPEND) != 0 ? '%' : '');
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return 'NULL';
        }

        static $escapeMap = [
            '_' => self::ESCAPE_CHAR . '_',
            '%' => self::ESCAPE_CHAR . '%',
            self::ESCAPE_CHAR => self::ESCAPE_CHAR . self::ESCAPE_CHAR,
        ];
        $pattern = $this->prefix . strtr($val, $escapeMap) . $this->postfix;

        return Types::serializeString($pattern);
    }
}
