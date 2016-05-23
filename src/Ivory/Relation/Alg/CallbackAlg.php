<?php
namespace Ivory\Relation\Alg;

abstract class CallbackAlg
{
    private $callback;

    /**
     * @param callable $callback
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    protected function call(...$args)
    {
        return call_user_func($this->callback, ...$args);
    }
}
