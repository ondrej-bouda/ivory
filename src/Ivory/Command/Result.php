<?php
namespace Ivory\Command;

class Result implements IResult
{
    /** @var resource result handler */
    private $handler;

    public function __construct($handler)
    {
        if (!is_resource($handler)) {
            throw new \InvalidArgumentException('$handler');
        }
        $this->handler = $handler;
    }


}
