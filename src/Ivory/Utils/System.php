<?php
namespace Ivory\Utils;

class System
{
    /**
     * @return bool <tt>true</tt> iff the operating system is Windows
     */
    public static function isWindows()
    {
        return (strncasecmp(PHP_OS, 'WIN', 3) == 0);
    }

    public static function is32Bit()
    {
        return (PHP_INT_SIZE == 4);
    }

    public static function is64Bit()
    {
        return (PHP_INT_SIZE == 8);
    }
}
