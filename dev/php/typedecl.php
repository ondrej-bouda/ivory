<?php
namespace Ivory\Dev\Php;

function foo(string $s, bool $b, int $i, float $f, /*null*/ $n)
{
    var_dump(func_get_args());
}

foo('a', true, 1, NAN, null);
