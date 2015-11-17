<?php
namespace Ivory\Relation\Alg;

class CallbackValueHasher extends CallbackAlg implements IValueHasher
{
    public function hash($value)
    {
        return $this->call($value);
    }
}
