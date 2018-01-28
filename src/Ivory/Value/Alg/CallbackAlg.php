<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

abstract class CallbackAlg
{
    private $callback;

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    protected function call(...$args)
    {
        return call_user_func($this->callback, ...$args);
    }
}
