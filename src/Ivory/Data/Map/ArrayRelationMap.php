<?php
namespace Ivory\Data\Map;

class ArrayRelationMap implements \IteratorAggregate, IRelationMap
{
    use ArrayMapMacros;

    //region ArrayMapMacros

    protected function isNestedMap($entry)
    {
        return ($entry instanceof IRelationMap);
    }

    //endregion
}