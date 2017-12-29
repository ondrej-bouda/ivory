<?php
declare(strict_types=1);
namespace Ivory\Relation\Alg;

class CallbackValueFilter extends CallbackAlg implements IValueFilter
{
    public function accept($value): bool
    {
        return (bool)$this->call($value);
    }
}
