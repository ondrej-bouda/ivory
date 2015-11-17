<?php
namespace Ivory\Relation;

abstract class StreamlinedRelation extends RelationBase
{
    protected $source;

    protected function __construct(IRelation $source)
    {
        $this->source = $source;
    }
}
