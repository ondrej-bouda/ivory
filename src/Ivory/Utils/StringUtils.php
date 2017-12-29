<?php
declare(strict_types=1);
namespace Ivory\Utils;

class StringUtils
{
    /**
     * Like {@link preg_replace_callback()}, it performs a string search and replace. The callback does not receive
     * plain array of matches, but an extended array: each item is a pair of the matching portion of the needle and the
     * byte offset to the subject, like {@link preg_match_all()} does with the `PREG_OFFSET_CAPTURE` flag.
     *
     * @param string $pattern
     * @param callable $callback
     * @param string|string[] $subject
     * @param int $limit
     * @param int|null $count
     * @return string|string[] depending on whether <tt>$subject</tt> is <tt>string</tt> or <tt>array</tt>,
     *                           a <tt>string</tt> or <tt>array</tt> is returned
     */
    public static function pregReplaceCallbackWithOffset(
        string $pattern,
        callable $callback,
        $subject,
        int $limit = -1,
        int &$count = null
    )
    {
        if (is_array($subject)) {
            $result = [];
            foreach ($subject as $item) {
                $result[] = self::pregReplaceCallbackWithOffsetImpl($pattern, $callback, $item, $limit, $count);
            }
            return $result;
        } else {
            return self::pregReplaceCallbackWithOffsetImpl($pattern, $callback, $subject, $limit, $count);
        }
    }

    private static function pregReplaceCallbackWithOffsetImpl(
        string $pattern,
        callable $callback,
        string $subject,
        int $limit = -1,
        int &$count = null
    ): string
    {
        $count = 0;

        $curOffset = 0;
        $result = '';
        preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $i => $match) {
            if ($limit >= 0 && $i >= $limit) {
                break;
            }

            $result .= substr($subject, $curOffset, $match[0][1] - $curOffset) . $callback($match);
            $curOffset = $match[0][1] + strlen($match[0][0]);
            $count++;
        }
        $result .= substr($subject, $curOffset);

        return $result;
    }

    /**
     * @param int $num
     * @return string "1st", "2nd", "3rd", "4th", etc. according to <tt>$num</tt>
     */
    public static function englishOrd(int $num): string
    {
        switch ($num) {
            case 1:
                return '1st';
            case 2:
                return '2nd';
            case 3:
                return '3rd';
            default:
                return $num . 'th';
        }
    }
}
