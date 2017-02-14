<?php
namespace Ivory\Relation\Alg;

class CallbackValueFilter extends CallbackAlg implements IValueFilter
{
    public function accept($value): bool
    {
        return $this->call($value);
    }
}
