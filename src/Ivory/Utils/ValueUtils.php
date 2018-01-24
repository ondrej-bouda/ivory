<?php
declare(strict_types=1);
namespace Ivory\Utils;

use Ivory\Exception\IncomparableException;

class ValueUtils
{
    public static function equals($a, $b): bool
    {
        if ($a === null) {
            return ($b === null);
        }

        if ($a instanceof IEqualable) {
            return $a->equals($b);
        } elseif (is_array($a)) {
            if (!is_array($b)) {
                return false;
            }
            if (count($a) != count($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (!array_key_exists($k, $b)) {
                    return false;
                }
                if (!self::equals($v, $b[$k])) {
                    return false;
                }
            }
            return true;
        } else {
            return ($a == $b);
        }
    }

    /**
     * Compares two values according to their type.
     *
     * @param mixed $a
     * @param mixed $b
     * @return int the comparison result: negative integer, zero, or positive integer if <tt>$this</tt> is less than,
     *               equal to, or greater than <tt>$other</tt>, respectively
     * @throws IncomparableException if the values are incomparable
     * @throws \InvalidArgumentException if either of values is <tt>null</tt>
     */
    public static function compareValues($a, $b): int
    {
        if ($a === null || $b === null) {
            throw new \InvalidArgumentException('comparing with null');
        }

        if (is_int($a) || is_bool($a)) {
            return ($a <=> $b);
        } elseif (is_float($a)) {
            return self::compareFloats($a, $b);
        } elseif (is_string($a)) {
            return strcmp($a, $b);
        } elseif ($a instanceof IComparable) {
            return $a->compareTo($b);
        } elseif (is_array($a) && is_array($b)) {
            return self::compareArrays($a, $b);
        } else {
            throw new IncomparableException();
        }
    }

    public static function compareBigIntegers($a, $b): int
    {
        if ($a > PHP_INT_MAX || $b > PHP_INT_MAX || $a < PHP_INT_MIN || $b < PHP_INT_MIN) {
            return bccomp($a, $b, 0);
        } else {
            return (int)$a - (int)$b;
        }
    }

    public static function compareFloats($a, $b): int
    {
        if ($a === NAN) {
            return ($b === NAN ? 0 : 1);
        } elseif ($b === NAN) {
            return -1;
        }

        return (float)$a <=> (float)$b;
    }

    public static function compareArrays(array $a, array $b): int
    {
        reset($b);
        foreach ($a as $av) {
            if (key($b) === null) {
                return 1;
            }
            $bv = current($b);
            next($b);
            if ($av === null) {
                if ($bv !== null) {
                    return -1;
                }
            } elseif ($bv === null) {
                return 1;
            } elseif (is_array($av)) {
                if (is_array($bv)) {
                    $comp = self::compareArrays($av, $bv);
                    if ($comp) {
                        return $comp;
                    }
                } else {
                    return 1;
                }
            } elseif (is_array($bv)) {
                return -1;
            } else {
                $comp = self::compareValues($av, $bv);
                if ($comp != 0) {
                    return $comp;
                }
            }
        }
        if (key($b) !== null) {
            return -1;
        }

        // ties broken by the subscripts of the first item
        $aFst = $a;
        $bFst = $b;
        do {
            reset($aFst);
            reset($bFst);
            $ak = key($aFst);
            $bk = key($bFst);
            if ($ak === null && $bk === null) {
                return 0;
            } elseif ($ak === null) {
                return -1;
            } elseif ($bk === null) {
                return 1;
            } elseif (!is_numeric($ak) || !is_numeric($bk)) {
                return 0;
            } else {
                $d = $ak - $bk;
                if ($d) {
                    return $d;
                } else {
                    $aFst = current($aFst);
                    $bFst = current($bFst);
                }
            }
        } while (is_array($aFst) && is_array($bFst));

        return 0;
    }
}
