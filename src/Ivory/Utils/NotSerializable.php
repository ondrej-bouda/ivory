<?php
namespace Ivory\Utils;

use Ivory\UnsupportedException;

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
