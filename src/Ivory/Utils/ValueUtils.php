<?php
namespace Ivory\Utils;

class ValueUtils
{
    public static function equals($a, $b)
    {
        if ($a === null) {
            return ($b === null);
        }

        if ($a instanceof IComparable) {
            return $a->equals($b);
        }
        elseif (is_array($a)) {
            if (!is_array($b)) {
                return false;
            }
            if (count($a) != count($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (!isset($b[$k])) {
                    return false;
                }
                if (!self::equals($v, $b[$k])) {
                    return false;
                }
            }
            return true;
        }
        else {
            return ($a == $b);
        }
    }
}
