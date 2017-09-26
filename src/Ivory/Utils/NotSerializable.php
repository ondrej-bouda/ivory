<?php
declare(strict_types=1);

namespace Ivory\Utils;

use Ivory\Exception\UnsupportedException;

trait NotSerializable
{
    public function __sleep()
    {
        throw new UnsupportedException(get_class($this) . ' cannot be serialized');
    }

    public function __wakeup()
    {
        throw new UnsupportedException(get_class($this) . ' cannot be unserialized');
    }
}
