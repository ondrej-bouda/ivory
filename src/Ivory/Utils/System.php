<?php
declare(strict_types=1);
namespace Ivory\Utils;

class System
{
    /**
     * @return bool <tt>true</tt> iff the operating system is Windows
     */
    public static function isWindows(): bool
    {
        return (strncasecmp(PHP_OS, 'WIN', 3) == 0);
    }

    public static function is32Bit(): bool
    {
        return (PHP_INT_SIZE == 4);
    }

    public static function is64Bit(): bool
    {
        return (PHP_INT_SIZE == 8);
    }

    /**
     * @return bool whether the GMP extension is available
     */
    public static function hasGMP(): bool
    {
        static $installed = null;
        if ($installed === null) {
            $installed = extension_loaded('gmp');
        }
        return $installed;
    }
}
