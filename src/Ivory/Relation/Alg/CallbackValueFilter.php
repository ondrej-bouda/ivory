<?php
namespace Ivory\Relation\Alg;

class CallbackValueFilter extends CallbackAlg implements IValueFilter
{
    public function accept($value)
    {
        return $this->call($value);
    }
}
